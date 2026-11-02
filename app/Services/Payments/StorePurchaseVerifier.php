<?php

namespace App\Services\Payments;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StorePurchaseVerifier
{
    public function configured(string $store): bool
    {
        $c = config("store_subscriptions.$store");

        return $store === 'apple'
            ? $c['issuer'] && $c['key_id'] && is_readable((string) $c['key_path']) && is_readable((string) $c['root_path'])
            : is_readable((string) $c['credentials_path']);
    }

    public function verify(string $store, string $reference, string $account): array
    {
        abort_unless($this->configured($store), 503, 'Store verification is not configured.');
        try {
            return $store === 'apple' ? $this->apple($reference, $account) : $this->google($reference, $account);
        } catch (ConnectionException $e) {
            abort(503, 'The store is temporarily unreachable.');
        }
    }

    private function google(string $token, string $account): array
    {
        $c = config('store_subscriptions.google');
        $bearer = $this->googleAccessToken($c);
        $url = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($c['package']).'/purchases/';
        $http = Http::withToken($bearer)->timeout(12);
        $response = $http->get($url.'subscriptionsv2/tokens/'.rawurlencode($token));
        abort_if(in_array($response->status(), [400, 404, 410], true), 422, 'Unknown Play subscription.');
        abort_unless($response->successful(), 503, 'Play verification unavailable.');
        $data = $response->json();
        abort_unless(hash_equals($account, (string) data_get($data, 'externalAccountIdentifiers.obfuscatedExternalAccountId')), 403, 'Purchase belongs to another account.');
        abort_if(isset($data['testPurchase']) && ! $c['allow_test'], 422, 'Test purchase in production.');
        $items = collect($data['lineItems'] ?? [])->map(function ($line) use ($c) {
            $id = $line['productId'] ?? '';
            $base = data_get($line, 'offerDetails.basePlanId');
            $specific = $id.':'.$base;
            $line['catalog_key'] = in_array($specific, $c['products'], true) ? $specific : $id;

            return $line;
        })->filter(fn ($line) => in_array($line['catalog_key'], $c['products'], true) && ! empty($line['expiryTime']))
            ->sortByDesc(fn ($line) => CarbonImmutable::parse($line['expiryTime'])->getTimestamp());
        $item = $items->first();
        abort_unless($item, 422, 'Unrecognized subscription product.');
        $end = CarbonImmutable::parse($item['expiryTime']);
        $active = $end->isFuture() && in_array($data['subscriptionState'] ?? '', [
            'SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD', 'SUBSCRIPTION_STATE_CANCELED',
        ], true);

        return ['reference' => $token, 'product' => $item['catalog_key'], 'end' => $end, 'active' => $active,
            'play_product' => $item['productId'],
            'acknowledge' => ($data['acknowledgementState'] ?? '') === 'ACKNOWLEDGEMENT_STATE_PENDING',
            'previous' => $data['linkedPurchaseToken'] ?? null];
    }

    private function googleAccessToken(array $c): string
    {
        return Cache::remember('play-publisher:'.hash('sha256', $c['credentials_path']), 3000, function () use ($c) {
            $key = json_decode(file_get_contents($c['credentials_path']), true);
            abort_unless(isset($key['client_email'], $key['private_key']), 503, 'Invalid Play service account configuration.');
            $endpoint = 'https://oauth2.googleapis.com/token';
            $jwt = JWT::encode(['iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/androidpublisher',
                'aud' => $endpoint, 'iat' => time(), 'exp' => time() + 3600], $key['private_key'], 'RS256');
            $response = Http::asForm()->timeout(10)->post($endpoint, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt,
            ]);
            abort_unless($response->successful() && $response->json('access_token'), 503, 'Play authentication failed.');

            return $response->json('access_token');
        });
    }

    public function acknowledgePlay(string $product, string $reference): void
    {
        $c = config('store_subscriptions.google');
        $url = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($c['package'])
            .'/purchases/subscriptions/'.rawurlencode($product).'/tokens/'.rawurlencode($reference).':acknowledge';
        try {
            $response = Http::withToken($this->googleAccessToken($c))->timeout(12)->withBody('{}', 'application/json')->post($url);
        } catch (ConnectionException $e) {
            abort(503, 'Play acknowledgement unavailable.');
        }
        abort_unless($response->successful(), 503, 'Play acknowledgement unavailable.');
    }

    private function apple(string $transaction, string $account): array
    {
        abort_unless(ctype_digit($transaction), 422, 'Invalid App Store transaction ID.');
        $c = config('store_subscriptions.apple');
        $environment = $c['sandbox'] ? 'Sandbox' : 'Production';
        $base = $c['sandbox'] ? 'https://api.storekit-sandbox.apple.com' : 'https://api.storekit.apple.com';
        $jwt = JWT::encode(['iss' => $c['issuer'], 'iat' => time(), 'exp' => time() + 300,
            'aud' => 'appstoreconnect-v1', 'bid' => $c['bundle']], file_get_contents($c['key_path']), 'ES256', $c['key_id']);
        $response = Http::withToken($jwt)->timeout(12)->get($base.'/inApps/v1/subscriptions/'.$transaction);
        abort_if(in_array($response->status(), [400, 404], true), 422, 'Unknown App Store transaction.');
        abort_unless($response->successful(), 503, 'App Store verification unavailable.');
        $candidates = [];
        foreach ($response->json('data', []) as $group) {
            foreach ($group['lastTransactions'] ?? [] as $row) {
                $tx = $this->applePayload($row['signedTransactionInfo'] ?? '', $c['root_path']);
                if (($tx['environment'] ?? '') !== $environment || ($tx['bundleId'] ?? '') !== $c['bundle']
                    || ! hash_equals($account, strtolower($tx['appAccountToken'] ?? ''))
                    || ! in_array($tx['productId'] ?? '', $c['products'], true)) {
                    continue;
                }
                $endMs = $tx['expiresDate'] ?? 0;
                if (($row['status'] ?? 0) === 4 && isset($row['signedRenewalInfo'])) {
                    $renewal = $this->applePayload($row['signedRenewalInfo'], $c['root_path']);
                    if (($renewal['originalTransactionId'] ?? null) === $tx['originalTransactionId']) {
                        $endMs = $renewal['gracePeriodExpiresDate'] ?? $endMs;
                    }
                }
                $end = CarbonImmutable::createFromTimestampMs($endMs);
                $candidates[] = ['transaction' => (string) $tx['transactionId'], 'reference' => (string) $tx['originalTransactionId'], 'product' => $tx['productId'],
                    'active' => in_array($row['status'], [1, 4], true) && empty($tx['revocationDate']) && $end->isFuture(),
                    'end' => $end];
            }
        }
        abort_unless($candidates, 422, 'No matching subscription for this app and account.');
        foreach ($candidates as $candidate) {
            if (in_array($transaction, [$candidate['transaction'], $candidate['reference']], true)) {
                return $candidate;
            }
        }
        // A restored historical renewal may not be the latest transaction.
        // Resolve its original chain before choosing among the account's groups.
        $lookup = Http::withToken($jwt)->timeout(12)->get($base.'/inApps/v1/transactions/'.$transaction);
        abort_unless($lookup->successful(), 503, 'App Store transaction lookup unavailable.');
        $original = $this->applePayload((string) $lookup->json('signedTransactionInfo'), $c['root_path']);
        $match = collect($candidates)->firstWhere('reference', (string) ($original['originalTransactionId'] ?? ''));
        abort_unless($match && ($original['transactionId'] ?? '') === $transaction &&
            ($original['environment'] ?? '') === $environment && ($original['bundleId'] ?? '') === $c['bundle']
            && hash_equals($account, strtolower($original['appAccountToken'] ?? '')), 422, 'Subscription chain mismatch.');

        return $match;
    }

    private function applePayload(string $jws, string $rootPath): array
    {
        // Only accept a StoreKit response from Apple's API and a signing chain
        // rooted in our independently configured Apple Root CA G3.
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jws)[0]), true);
        $chain = $header['x5c'] ?? [];
        abort_unless(count($chain) === 3 && ($header['alg'] ?? '') === 'ES256', 503, 'Invalid App Store certificate chain.');
        $pem = fn ($cert) => "-----BEGIN CERTIFICATE-----\n".chunk_split($cert, 64, "\n")."-----END CERTIFICATE-----\n";
        $leaf = $pem($chain[0]);
        $intermediate = $pem($chain[1]);
        $root = file_get_contents($rootPath);
        abort_unless(openssl_x509_fingerprint($root, 'sha256') === openssl_x509_fingerprint($pem($chain[2]), 'sha256')
            && openssl_x509_verify($intermediate, $root) === 1 && openssl_x509_verify($leaf, $intermediate) === 1
            && isset(openssl_x509_parse($leaf)['extensions']['1.2.840.113635.100.6.11.1'])
            && isset(openssl_x509_parse($intermediate)['extensions']['1.2.840.113635.100.6.2.1']),
            503, 'Untrusted App Store signature.');
        try {
            $payload = (array) JWT::decode($jws, new Key($leaf, 'ES256'));
        } catch (\Exception $e) {
            abort(503, 'Invalid App Store signature.');
        }
        $issued = (int) (($payload['signedDate'] ?? 0) / 1000);
        foreach ([$leaf, $intermediate, $root] as $certificate) {
            $validity = openssl_x509_parse($certificate);
            abort_unless($issued <= time() + 60 && $issued >= $validity['validFrom_time_t'] && $issued <= $validity['validTo_time_t'], 503, 'Invalid App Store signing date.');
        }

        return $payload;
    }
}

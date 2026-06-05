<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialIdentityVerifier
{
    public function verify(string $provider, string $idToken): array
    {
        return match ($provider) {
            'google' => $this->verifyGoogle($idToken),
            'apple' => $this->verifyApple($idToken),
            default => throw ValidationException::withMessages([
                'provider' => ['Unsupported social provider.'],
            ]),
        };
    }

    private function verifyGoogle(string $idToken): array
    {
        $payload = $this->decodeJwtWithJwks(
            $idToken,
            (string) config('services.google.jwks_url', 'https://www.googleapis.com/oauth2/v3/certs'),
            'google'
        );

        $this->assertIssuer($payload, ['https://accounts.google.com', 'accounts.google.com'], 'google');
        $this->assertAudience($payload, $this->allowedClientIds('google'), 'google');
        $this->assertSubject($payload, 'google');

        return [
            'provider_user_id' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? strtolower((string) $payload['email']) : null,
            'email_verified' => $this->truthy($payload['email_verified'] ?? false),
            'name' => $payload['name'] ?? null,
            'first_name' => $payload['given_name'] ?? null,
            'last_name' => $payload['family_name'] ?? null,
            'avatar' => $payload['picture'] ?? null,
            'provider_data' => $payload,
        ];
    }

    private function verifyApple(string $idToken): array
    {
        $payload = $this->decodeJwtWithJwks(
            $idToken,
            (string) config('services.apple.jwks_url', 'https://appleid.apple.com/auth/keys'),
            'apple'
        );

        $this->assertIssuer($payload, [(string) config('services.apple.issuer', 'https://appleid.apple.com')], 'apple');
        $this->assertAudience($payload, $this->allowedClientIds('apple'), 'apple');
        $this->assertSubject($payload, 'apple');

        return [
            'provider_user_id' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? strtolower((string) $payload['email']) : null,
            'email_verified' => $this->truthy($payload['email_verified'] ?? false),
            'name' => null,
            'first_name' => null,
            'last_name' => null,
            'avatar' => null,
            'provider_data' => $payload,
        ];
    }

    private function decodeJwtWithJwks(string $idToken, string $jwksUrl, string $provider): array
    {
        try {
            JWT::$leeway = 60;

            $jwks = Cache::remember('social_auth_jwks_' . md5($provider . ':' . $jwksUrl), now()->addHours(6), function () use ($jwksUrl) {
                $response = Http::acceptJson()->timeout(10)->get($jwksUrl);

                if ($response->failed()) {
                    throw ValidationException::withMessages([
                        'id_token' => ['Unable to validate the social login token right now.'],
                    ]);
                }

                return $response->json() ?? [];
            });

            $payload = JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));

            return json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'id_token' => ['The ' . $provider . ' identity token is invalid.'],
            ]);
        }
    }

    private function allowedClientIds(string $provider): array
    {
        $clientIds = array_values(array_filter((array) config('services.' . $provider . '.client_ids', [])));

        if ($clientIds === []) {
            throw ValidationException::withMessages([
                'provider' => [ucfirst($provider) . ' login is not configured on this server.'],
            ]);
        }

        return $clientIds;
    }

    private function assertAudience(array $payload, array $allowedAudiences, string $provider): void
    {
        $audience = $payload['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $audiences = array_values(array_filter($audiences, fn($value) => is_string($value) && $value !== ''));

        if ($audiences === [] || array_intersect($audiences, $allowedAudiences) === []) {
            throw ValidationException::withMessages([
                'id_token' => ['The ' . $provider . ' identity token was issued for a different client.'],
            ]);
        }
    }

    private function assertIssuer(array $payload, array $allowedIssuers, string $provider): void
    {
        $issuer = $payload['iss'] ?? null;

        if (! is_string($issuer) || ! in_array($issuer, $allowedIssuers, true)) {
            throw ValidationException::withMessages([
                'id_token' => ['The ' . $provider . ' identity token issuer is invalid.'],
            ]);
        }
    }

    private function assertSubject(array $payload, string $provider): void
    {
        if (! isset($payload['sub']) || ! is_string($payload['sub']) || trim($payload['sub']) === '') {
            throw ValidationException::withMessages([
                'id_token' => ['The ' . $provider . ' identity token is missing the subject claim.'],
            ]);
        }
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return false;
    }
}

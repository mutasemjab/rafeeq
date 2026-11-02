<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private array $temporary = [];

    private array $play;

    private string $reference = 'play-e2e-token';

    private int $acknowledgements = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->seed(PlanSeeder::class);
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'user-api');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function file(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rafiq-store-');
        file_put_contents($file, $contents);
        $this->temporary[] = $file;

        return $file;
    }

    private function playStore(): void
    {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048]), $private);
        config(['store_subscriptions.google.credentials_path' => $this->file(json_encode(['private_key' => $private, 'client_email' => 'test@example.test'])),
            'store_subscriptions.google.products' => ['pro-monthly' => 'rafiq:month', 'pro-yearly' => 'rafiq:year']]);
        $account = $this->getJson('/api/v1/subscription/store?store=google')->assertOk()
            ->assertJsonPath('products.pro-yearly', 'rafiq:year')->json('account_token');
        $this->play = ['subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE', 'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $account],
            'lineItems' => [['productId' => 'rafiq', 'offerDetails' => ['basePlanId' => 'year'], 'expiryTime' => now()->addYear()->toISOString()]]];
        Http::fake(function (Request $r) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'play-access']);
            }
            $this->assertTrue($r->hasHeader('Authorization', 'Bearer play-access'));
            if (str_ends_with($r->url(), 'subscriptionsv2/tokens/'.$this->reference)) {
                return Http::response($this->play);
            }
            $this->assertStringEndsWith('subscriptions/rafiq/tokens/'.$this->reference.':acknowledge', $r->url());
            $this->acknowledgements++;
            $this->play['acknowledgementState'] = 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';

            return Http::response('', 204);
        });
    }

    private function verify(string $store = 'google', ?string $id = null)
    {
        return $this->postJson('/api/v1/subscription/store/verify', ['store' => $store, 'reference' => $id ?? $this->reference]);
    }

    public function test_play_purchase_replay_restore_and_refund(): void
    {
        $this->playStore();
        $this->verify()->assertOk()->assertJsonPath('plan.slug', 'pro-yearly')->assertJsonPath('has_pro', true);
        $this->verify()->assertOk()->assertJsonPath('status', 'active');
        $this->assertSame(1, $this->acknowledgements);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertNotSame($this->reference, DB::table('subscriptions')->value('store_reference'));
        $this->getJson('/api/v1/chat/usage')->assertJsonPath('can_use_extra_thinking', true);
        $this->play['subscriptionState'] = 'SUBSCRIPTION_STATE_EXPIRED';
        $this->artisan('subscriptions:refresh-stores')->assertSuccessful();
        $this->getJson('/api/v1/chat/usage')->assertJsonPath('can_use_extra_thinking', false);
        $this->assertSame('expired', Subscription::first()->status);
    }

    public function test_play_rejects_another_account_unknown_base_plan_and_test_receipt(): void
    {
        $this->playStore();
        $this->play['testPurchase'] = [];
        $this->verify()->assertStatus(422);
        unset($this->play['testPurchase']);
        $this->play['lineItems'][0]['offerDetails']['basePlanId'] = 'foreign';
        $this->verify()->assertStatus(422);
        $this->actingAs(User::factory()->create(), 'user-api');
        $this->getJson('/api/v1/subscription/store?store=google')->assertOk();
        $this->verify()->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(0, $this->acknowledgements);
    }

    public function test_apple_signed_transaction_renewal_and_revocation(): void
    {
        // A local test CA and the same Apple signing-purpose OIDs exercise the
        // entire verifier without a store account or external requests.
        $conf = $this->file("[req]\ndistinguished_name=dn\n[dn]\n[root]\nbasicConstraints=critical,CA:true\n[inter]\nbasicConstraints=critical,CA:true\n1.2.840.113635.100.6.2.1=ASN1:NULL\n[leaf]\nbasicConstraints=critical,CA:false\n1.2.840.113635.100.6.11.1=ASN1:NULL\n");
        $parentCert = null;
        $parentKey = null;
        $chain = [];
        foreach (['root', 'inter', 'leaf'] as $i => $role) {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            $csr = openssl_csr_new(['commonName' => $role], $key, ['config' => $conf, 'digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, $parentCert, $parentKey ?? $key, 5,
                ['config' => $conf, 'digest_alg' => 'sha256', 'x509_extensions' => $role], $i + 1);
            openssl_x509_export($cert, $pem);
            if ($role === 'root') {
                $rootPath = $this->file($pem);
            }
            $chain[] = preg_replace('/-----[^-]+-----|\s+/', '', $pem);
            $parentCert = $cert;
            $parentKey = $key;
        }
        openssl_pkey_export($key, $private);
        config(['store_subscriptions.apple.key_path' => $this->file($private), 'store_subscriptions.apple.root_path' => $rootPath,
            'store_subscriptions.apple.issuer' => 'issuer', 'store_subscriptions.apple.key_id' => 'key',
            'store_subscriptions.apple.products' => ['pro-monthly' => 'pro.month']]);
        $account = $this->getJson('/api/v1/subscription/store?store=apple')->assertOk()->json('account_token');
        $tx = ['signedDate' => time() * 1000, 'bundleId' => 'com.rafiq.user', 'environment' => 'Production', 'appAccountToken' => $account,
            'originalTransactionId' => '100', 'transactionId' => '101', 'productId' => 'pro.month', 'expiresDate' => now()->addMonth()->timestamp * 1000];
        $status = 1;
        $tamper = false;
        Http::fake(function (Request $request) use (&$tx, &$status, &$tamper, $private, $chain) {
            $this->assertStringStartsWith('https://api.storekit.apple.com/inApps/v1/subscriptions/', $request->url());
            $jws = JWT::encode($tx, $private, 'ES256', null, ['x5c' => array_reverse($chain)]);
            if ($tamper) {
                $parts = explode('.', $jws);
                $parts[1] = JWT::urlsafeB64Encode(json_encode(array_merge($tx, ['productId' => 'forged'])));
                $jws = implode('.', $parts);
            }

            return Http::response(['data' => [['lastTransactions' => [['status' => $status, 'signedTransactionInfo' => $jws]]]]]);
        });
        $this->verify('apple', '101')->assertOk()->assertJsonPath('plan.slug', 'pro-monthly');
        $tamper = true;
        $this->verify('apple', '101')->assertStatus(503);
        $tamper = false;
        $status = 5;
        $tx['revocationDate'] = time() * 1000;
        $this->artisan('subscriptions:refresh-stores')->assertSuccessful();
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame('expired', Subscription::first()->status);
        $tx['appAccountToken'] = (string) Str::uuid();
        $this->verify('apple', '101')->assertStatus(422);
    }
}

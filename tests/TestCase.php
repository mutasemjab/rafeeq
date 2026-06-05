<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUpPassport(): void
    {
        $this->loadPassportKeys();
        $this->createPassportPersonalAccessClient();
    }

    private function loadPassportKeys(): void
    {
        $directory = storage_path('framework/testing/passport');
        $privateKeyPath = $directory . DIRECTORY_SEPARATOR . 'oauth-private.key';
        $publicKeyPath = $directory . DIRECTORY_SEPARATOR . 'oauth-public.key';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the Passport test key directory.');
        }

        if (! file_exists($privateKeyPath) || ! file_exists($publicKeyPath)) {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if ($key === false) {
                throw new RuntimeException('Unable to generate Passport test keys.');
            }

            if (! openssl_pkey_export($key, $privateKey)) {
                throw new RuntimeException('Unable to export the Passport private key.');
            }

            $details = openssl_pkey_get_details($key);

            if ($details === false || ! isset($details['key'])) {
                throw new RuntimeException('Unable to export the Passport public key.');
            }

            file_put_contents($privateKeyPath, $privateKey);
            file_put_contents($publicKeyPath, $details['key']);
            @chmod($privateKeyPath, 0600);
            @chmod($publicKeyPath, 0644);
        }

        Passport::loadKeysFrom($directory);
    }

    private function createPassportPersonalAccessClient(): void
    {
        $personalAccessClient = Passport::personalAccessClient();

        if ($personalAccessClient->newQuery()->exists()) {
            return;
        }

        app(ClientRepository::class)->createPersonalAccessClient(
            null,
            'Test Personal Access Client',
            config('app.url', 'http://localhost')
        );
    }
}

<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServicesConfigTest extends TestCase
{
    public function test_apple_client_ids_fall_back_to_bundle_id(): void
    {
        $originalClientIds = getenv('APPLE_CLIENT_IDS');
        $originalClientId = getenv('APPLE_CLIENT_ID');
        $originalBundleId = getenv('APPLE_BUNDLE_ID');

        $this->setEnvValue('APPLE_CLIENT_IDS', '');
        $this->setEnvValue('APPLE_CLIENT_ID', '');
        $this->setEnvValue('APPLE_BUNDLE_ID', 'com.rafiq.user');

        $config = require base_path('config/services.php');

        $this->assertSame(['com.rafiq.user'], $config['apple']['client_ids']);

        $this->restoreEnvValue('APPLE_CLIENT_IDS', $originalClientIds);
        $this->restoreEnvValue('APPLE_CLIENT_ID', $originalClientId);
        $this->restoreEnvValue('APPLE_BUNDLE_ID', $originalBundleId);
    }

    public function test_apple_client_ids_are_trimmed_and_deduplicated(): void
    {
        $originalClientIds = getenv('APPLE_CLIENT_IDS');
        $originalClientId = getenv('APPLE_CLIENT_ID');
        $originalBundleId = getenv('APPLE_BUNDLE_ID');

        $this->setEnvValue('APPLE_CLIENT_IDS', ' com.rafiq.user , com.rafiq.web , com.rafiq.user ');
        $this->setEnvValue('APPLE_CLIENT_ID', '');
        $this->setEnvValue('APPLE_BUNDLE_ID', 'com.rafiq.user');

        $config = require base_path('config/services.php');

        $this->assertSame(['com.rafiq.user', 'com.rafiq.web'], $config['apple']['client_ids']);

        $this->restoreEnvValue('APPLE_CLIENT_IDS', $originalClientIds);
        $this->restoreEnvValue('APPLE_CLIENT_ID', $originalClientId);
        $this->restoreEnvValue('APPLE_BUNDLE_ID', $originalBundleId);
    }

    private function setEnvValue(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function restoreEnvValue(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

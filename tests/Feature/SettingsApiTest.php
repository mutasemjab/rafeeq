<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_api_returns_payment_flags_for_mobile(): void
    {
        config()->set('payments.pay_for_later_enabled', true);

        AppSetting::query()->create([
            'key' => 'mobile_payments_enabled',
            'value' => '0',
        ]);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('payments.mobile_enabled', false)
            ->assertJsonPath('payments.pay_for_later_enabled', true)
            ->assertJsonPath('payments.available_methods', []);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_mobile_payments_from_dashboard(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        auth()->shouldUse('admin');
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.settings.payments.update'), [
            'mobile_payments_enabled' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'mobile_payments_enabled',
            'value' => '0',
        ]);
    }
}

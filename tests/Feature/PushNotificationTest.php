<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user = User::factory()->create();
    }

    public function test_user_can_register_push_token(): void
    {
        $response = $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/devices/push-token', [
                'push_token' => 'fcm-token-123',
                'platform' => 'android',
                'app_version' => '1.0.0',
            ])
            ->assertOk();

        $response->assertJsonPath('device.push_token', 'fcm-token-123');
        $response->assertJsonPath('device.platform', 'android');

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'push_token' => 'fcm-token-123',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);
    }

    public function test_user_can_remove_push_token(): void
    {
        UserDevice::query()->create([
            'user_id' => $this->user->id,
            'platform' => 'ios',
            'push_token' => 'remove-me-token',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson('/api/v1/devices/push-token', [
                'push_token' => 'remove-me-token',
            ])
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('user_devices', [
            'user_id' => $this->user->id,
            'push_token' => 'remove-me-token',
        ]);
    }
}

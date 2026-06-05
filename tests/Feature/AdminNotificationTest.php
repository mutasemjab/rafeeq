<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\NotificationsController;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\FirebaseMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notifications_page_renders_send_form(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        $this->mock(FirebaseMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        auth()->shouldUse('admin');
        $this->actingAs($admin, 'admin');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $view = app(NotificationsController::class)->index(app(FirebaseMessagingService::class));
        $html = $view->render();

        $this->assertStringContainsString('Send New Notification', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('name="body"', $html);
    }

    public function test_admin_can_send_notification_to_specific_user(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        UserDevice::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'push_token' => 'target-device-token',
            'last_seen_at' => now(),
        ]);

        $this->mock(FirebaseMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('sendToToken')
                ->once()
                ->with('target-device-token', 'Important update', 'Please open your appointment details.', ['screen' => 'appointments'])
                ->andReturn(['ok' => true, 'message_id' => 'projects/rafiq/messages/test']);
        });

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.store'), [
                'audience' => 'user',
                'user_id' => $user->id,
                'type' => 'admin_broadcast',
                'title' => 'Important update',
                'body' => 'Please open your appointment details.',
                'data_json' => '{"screen":"appointments"}',
                'send_push' => '1',
            ]);

        $response->assertRedirect(route('admin.notifications.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rafiq_notifications', [
            'user_id' => $user->id,
            'type' => 'admin_broadcast',
            'title' => 'Important update',
            'body' => 'Please open your appointment details.',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\RafiqNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_own_notifications(): void
    {
        RafiqNotification::factory()->count(4)->create(['user_id' => $this->user->id]);
        RafiqNotification::factory()->count(2)->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user, 'user-api')
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $this->assertCount(4, $response->json('data'));
    }

    public function test_user_can_mark_notification_read(): void
    {
        $notification = RafiqNotification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $otherUser    = User::factory()->create();
        $notification = RafiqNotification::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(403);
    }

    public function test_mark_all_read(): void
    {
        RafiqNotification::factory()->count(3)->create(['user_id' => $this->user->id, 'read_at' => null]);

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $unread = RafiqNotification::where('user_id', $this->user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unread);
    }
}

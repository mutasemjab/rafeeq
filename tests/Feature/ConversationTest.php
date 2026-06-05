<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user      = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_user_can_create_conversation(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/conversations', ['title' => 'Test Chat'])
            ->assertStatus(201)
            ->assertJsonPath('title', 'Test Chat');

        $this->assertDatabaseHas('conversations', [
            'user_id' => $this->user->id,
            'title'   => 'Test Chat',
            'source'  => 'text',
        ]);
    }

    public function test_user_can_create_conversation_with_legacy_source_aliases(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/conversations', [
                'title' => 'Web Chat',
                'source' => 'web',
            ])
            ->assertStatus(201)
            ->assertJsonPath('source', 'text');

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/conversations', [
                'title' => 'Voice Chat',
                'source' => 'voice',
            ])
            ->assertStatus(201)
            ->assertJsonPath('source', 'voice');

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/conversations', [
                'title' => 'App Chat',
                'source' => 'app',
            ])
            ->assertStatus(201)
            ->assertJsonPath('source', 'text');
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $conv = Conversation::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/conversations/{$conv->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        $conv = Conversation::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/conversations/{$conv->id}")
            ->assertStatus(403);
    }

    public function test_user_can_list_own_conversations(): void
    {
        Conversation::factory()->count(3)->create(['user_id' => $this->user->id]);
        Conversation::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'user-api')
            ->getJson('/api/v1/conversations')
            ->assertOk();

        $this->assertCount(3, $response->json('data'));
    }
}

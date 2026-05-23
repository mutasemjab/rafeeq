<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('public');
        Queue::fake();
    }

    public function test_user_can_upload_attachment(): void
    {
        $conv = Conversation::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/attachments', [
                'conversation_id' => $conv->id,
                'file'            => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'uploaded');
    }

    public function test_max_5_attachments_per_conversation(): void
    {
        $conv = Conversation::factory()->create(['user_id' => $this->user->id]);

        ChatAttachment::factory()->count(5)->create([
            'user_id'         => $this->user->id,
            'conversation_id' => $conv->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/attachments', [
                'conversation_id' => $conv->id,
                'file'            => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_user_cannot_upload_to_another_users_conversation(): void
    {
        $otherUser = User::factory()->create();
        $conv      = Conversation::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/attachments', [
                'conversation_id' => $conv->id,
                'file'            => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_attachment(): void
    {
        $otherUser  = User::factory()->create();
        $conv       = Conversation::factory()->create(['user_id' => $otherUser->id]);
        $attachment = ChatAttachment::factory()->create([
            'user_id'         => $otherUser->id,
            'conversation_id' => $conv->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/attachments/{$attachment->id}")
            ->assertStatus(403);
    }

    public function test_attachment_scoped_to_user_and_conversation(): void
    {
        $conv1 = Conversation::factory()->create(['user_id' => $this->user->id]);
        $conv2 = Conversation::factory()->create(['user_id' => $this->user->id]);

        ChatAttachment::factory()->create(['user_id' => $this->user->id, 'conversation_id' => $conv1->id]);
        ChatAttachment::factory()->create(['user_id' => $this->user->id, 'conversation_id' => $conv2->id]);

        $response = $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/conversations/{$conv1->id}/attachments")
            ->assertOk();

        $this->assertCount(1, $response->json());
    }
}

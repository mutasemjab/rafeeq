<?php

namespace Tests\Feature;

use App\Exceptions\ChatServiceUnavailableException;
use App\Jobs\ProcessChatTurnJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ChatTurnTransportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Bus::fake();
        $this->user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $this->conversation = Conversation::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user, 'user-api');
    }

    private function send(string $id = 'turn-1', string $message = 'Help me understand this report.')
    {
        return $this->postJson("/api/v1/conversations/{$this->conversation->id}/chat", [
            'message' => $message, 'client_message_id' => $id, 'language' => 'en', 'async' => true,
        ]);
    }

    public function test_async_accept_and_replay_persist_one_message_and_one_job(): void
    {
        $this->send()->assertStatus(202)->assertJsonPath('turn.status', 'queued');
        $this->send()->assertStatus(202)->assertJsonPath('turn.client_message_id', 'turn-1');
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('chat_turns', 1);
        Bus::assertDispatchedAfterResponse(ProcessChatTurnJob::class, 1);
    }

    public function test_id_cannot_be_reused_for_different_content_and_parallel_turn_is_rejected(): void
    {
        $this->send()->assertStatus(202);
        $this->send('turn-1', 'Different message')->assertStatus(409);
        $this->send('turn-2')->assertStatus(409);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_completed_response_can_be_recovered_and_replayed_without_regeneration(): void
    {
        $this->send()->assertStatus(202);
        $turn = ChatTurn::firstOrFail();
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldReceive('ask')->once()->andReturnUsing(function ($conversation, $text, $uid, $child, $language, $userMessage, $progress) {
            $progress('reviewing_reply');

            return Message::create(['conversation_id' => $conversation->id, 'user_id' => $uid,
                'role' => 'assistant', 'content' => 'Recovered reply', 'reply_to_message_id' => $userMessage->id]);
        });
        app(ChatTurnService::class)->process($turn, $chat);
        app(ChatTurnService::class)->process($turn, $chat);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")
            ->assertOk()->assertJsonPath('turn.status', 'completed')
            ->assertJsonPath('turn.message.content', 'Recovered reply')
            ->assertJsonPath('turn.message.client_message_id', 'turn-1');
        $this->send()->assertOk()->assertJsonPath('turn.message.content', 'Recovered reply');
        $this->assertDatabaseCount('messages', 2);
        Bus::assertDispatchedAfterResponse(ProcessChatTurnJob::class, 1);
    }

    public function test_failure_preserves_user_message_and_same_id_retry_reuses_it(): void
    {
        $this->send()->assertStatus(202);
        $turn = ChatTurn::firstOrFail();
        $userMessageId = $turn->user_message_id;
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldReceive('ask')->once()->andThrow(new ChatServiceUnavailableException('answer_generation', 'Please retry.'));
        app(ChatTurnService::class)->process($turn, $chat);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")
            ->assertOk()->assertJsonPath('turn.status', 'failed')->assertJsonPath('turn.error.retryable', true);
        $this->send()->assertStatus(202)->assertJsonPath('turn.user_message_id', $userMessageId);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('chat_turns', 1);
    }

    public function test_status_and_cancellation_are_owned_and_cancelled_job_does_not_generate(): void
    {
        $this->send()->assertStatus(202);
        $other = User::factory()->create();
        $this->actingAs($other, 'user-api')->getJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")->assertForbidden();
        $this->actingAs($this->user, 'user-api')->deleteJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")
            ->assertOk()->assertJsonPath('turn.status', 'cancelled');
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldNotReceive('ask');
        app(ChatTurnService::class)->process(ChatTurn::firstOrFail(), $chat);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_usage_is_daily_account_total_and_existing_turn_replay_ignores_quota(): void
    {
        $plan = Plan::factory()->create(['ai_messages_per_day' => 1]);
        Subscription::create(['user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()]);
        $this->send()->assertStatus(202);
        $this->getJson('/api/v1/chat/usage')->assertOk()->assertJsonPath('used', 1)->assertJsonPath('limit', 1)->assertJsonPath('remaining', 0);
        $this->send()->assertStatus(202);
        $otherConversation = Conversation::factory()->create(['user_id' => $this->user->id]);
        $this->postJson("/api/v1/conversations/{$otherConversation->id}/chat", [
            'message' => 'Another message', 'async' => true, 'client_message_id' => 'turn-2',
        ])->assertStatus(429);
        Message::create(['conversation_id' => $otherConversation->id, 'user_id' => $this->user->id,
            'role' => 'user', 'content' => 'Yesterday', 'created_at' => now()->subDay()]);
        // Eloquent's fillable deliberately excludes timestamps; set the historical fixture explicitly.
        Message::latest('id')->first()->forceFill(['created_at' => now()->subDay()])->save();
        $this->getJson('/api/v1/chat/usage')->assertJsonPath('used', 1);
    }

    public function test_stalled_turn_becomes_retryable_and_renewed_queue_lease_is_not_expired(): void
    {
        $this->send()->assertStatus(202);
        ChatTurn::first()->forceFill(['updated_at' => now()->subHour()])->save();
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")
            ->assertOk()->assertJsonPath('turn.status', 'failed');
        $this->send()->assertStatus(202);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/chat/turns/turn-1")
            ->assertStatus(202)->assertJsonPath('turn.status', 'queued');
    }

    public function test_consent_revoked_after_queueing_prevents_generation(): void
    {
        $this->send()->assertStatus(202);
        $this->user->forceFill(['ai_consent_accepted_at' => null])->save();
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldNotReceive('ask');
        app(ChatTurnService::class)->process(ChatTurn::firstOrFail(), $chat);
        $this->assertSame('failed', ChatTurn::first()->status);
    }

    public function test_old_failed_retry_returns_actionable_409_and_original_language_is_exposed(): void
    {
        $this->send()->assertStatus(202);
        $turn = ChatTurn::firstOrFail();
        app(ChatTurnService::class)->fail($turn, 'test_failure', 'Please retry.');
        $this->send('newer-turn')->assertStatus(202);
        app(ChatTurnService::class)->fail(ChatTurn::where('client_message_id', 'newer-turn')->first(), 'test_failure', 'Please retry.');
        $this->send()->assertStatus(409)->assertJsonPath('error_code', 'CHAT_TURN_SUPERSEDED')->assertJsonPath('retryable', false);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}")
            ->assertOk()->assertJsonPath('messages.0.language', 'en');
    }
}

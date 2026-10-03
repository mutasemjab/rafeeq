<?php

namespace Tests\Feature;

use App\Jobs\ProcessChatTurnJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Mockery;
use Tests\TestCase;

class ChatTurnRecoverySafetyTest extends TestCase
{
    use RefreshDatabase;

    private function accept(): array
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        [$turn] = app(ChatTurnService::class)->accept($conversation, $user, 'request-one', 'Please help.', 'en');

        return [$user, $conversation, $turn];
    }

    private function savedReply(ChatTurn $turn): Message
    {
        return Message::create([
            'conversation_id' => $turn->conversation_id, 'user_id' => $turn->user_id,
            'role' => 'assistant', 'content' => 'Saved before the worker stopped.',
            'reply_to_message_id' => $turn->user_message_id,
        ]);
    }

    public function test_poll_recovers_a_reply_persisted_before_the_completion_record(): void
    {
        [, , $turn] = $this->accept();
        $turn->update(['status' => 'processing', 'started_at' => now()]);
        $reply = $this->savedReply($turn);
        app(ChatTurnService::class)->expireStalled($turn);
        $this->assertSame('completed', $turn->status);
        $this->assertSame($reply->id, $turn->assistant_message_id);
        $this->assertSame('completed', $turn->userMessage->metadata['delivery_status']);
        $this->assertSame('request-one', $reply->fresh()->metadata['client_message_id']);
    }

    public function test_retry_after_a_crash_returns_the_saved_answer_without_redispatch(): void
    {
        [$user, $conversation, $turn] = $this->accept();
        $turn->update(['status' => 'failed']);
        $reply = $this->savedReply($turn);
        [$replayed, $dispatch] = app(ChatTurnService::class)->accept($conversation, $user, 'request-one', 'Please help.', 'en');
        $this->assertFalse($dispatch);
        $this->assertSame('completed', $replayed->status);
        $this->assertSame($reply->id, $replayed->assistant_message_id);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_an_old_job_and_its_failure_callback_cannot_affect_a_new_attempt(): void
    {
        [$user, $conversation, $turn] = $this->accept();
        $oldJob = new ProcessChatTurnJob($turn->id, $turn->attempt);
        $service = app(ChatTurnService::class);
        $service->fail($turn, 'temporary', 'Retry.');
        [$retry] = $service->accept($conversation, $user, 'request-one', 'Please help.', 'en');
        $this->assertSame(2, $retry->attempt);
        $this->assertSame('queued', $retry->userMessage->metadata['delivery_status']);
        $this->assertSame('en', $retry->userMessage->metadata['language']);
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldNotReceive('ask');
        $oldJob->handle($service, $chat);
        $oldJob->failed(new \RuntimeException('Old worker shutdown.'));
        $this->assertSame('queued', $retry->fresh()->status);
        $this->assertSame(2, $retry->fresh()->attempt);
    }

    public function test_an_old_poll_snapshot_cannot_expire_a_fresh_heartbeat(): void
    {
        [, , $turn] = $this->accept();
        $turn->forceFill(['status' => 'processing', 'started_at' => now()->subHour(), 'updated_at' => now()->subHour()])->save();
        $staleSnapshot = $turn->fresh();
        ChatTurn::whereKey($turn->id)->update(['updated_at' => now(), 'stage' => 'reviewing_reply']);
        app(ChatTurnService::class)->expireStalled($staleSnapshot);
        $this->assertSame('processing', $staleSnapshot->status);
        $this->assertSame('reviewing_reply', $staleSnapshot->stage);
    }

    public function test_retrying_an_old_unanswered_turn_after_newer_messages_is_rejected(): void
    {
        [$user, $conversation, $turn] = $this->accept();
        $service = app(ChatTurnService::class);
        $service->fail($turn, 'temporary', 'Retry.');
        [$newer] = $service->accept($conversation, $user, 'request-two', 'A newer concern.', 'en');
        $newer->update(['status' => 'cancelled']);
        try {
            $service->accept($conversation, $user, 'request-one', 'Please help.', 'en');
            $this->fail('Expected an ordering conflict.');
        } catch (HttpResponseException $error) {
            $this->assertSame(409, $error->getResponse()->getStatusCode());
            $payload = $error->getResponse()->getData(true);
            $this->assertSame('CHAT_TURN_SUPERSEDED', $payload['error_code']);
            $this->assertFalse($payload['retryable']);
            $this->assertStringContainsString('newer messages', $payload['message']);
        }
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_consent_revocation_during_generation_stops_the_final_progress_checkpoint(): void
    {
        [$user, , $turn] = $this->accept();
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldReceive('ask')->once()->andReturnUsing(function ($conversation, $text, $uid, $child, $language, $message, $progress) use ($user) {
            $user->forceFill(['ai_consent_accepted_at' => null])->save();
            $progress('persisting_reply');
            $this->fail('The final checkpoint should have stopped persistence.');
        });
        app(ChatTurnService::class)->process($turn, $chat);
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_an_unrelated_assistant_message_cannot_be_associated_with_this_turn(): void
    {
        [, , $turn] = $this->accept();
        $otherUser = User::factory()->create();
        $otherConversation = Conversation::factory()->create(['user_id' => $otherUser->id]);
        $foreign = Message::create(['conversation_id' => $otherConversation->id, 'user_id' => $otherUser->id, 'role' => 'assistant', 'content' => 'Private other reply.']);
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldReceive('ask')->once()->andReturn($foreign);
        app(ChatTurnService::class)->process($turn, $chat);
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertNull($turn->fresh()->assistant_message_id);
    }

    public function test_job_failure_recovers_a_saved_reply_instead_of_overwriting_it_with_failure(): void
    {
        [, , $turn] = $this->accept();
        $turn->update(['status' => 'processing']);
        $reply = $this->savedReply($turn);
        (new ProcessChatTurnJob($turn->id, $turn->attempt))->failed(new \RuntimeException('Crashed after save.'));
        $this->assertSame('completed', $turn->fresh()->status);
        $this->assertSame($reply->id, $turn->fresh()->assistant_message_id);
    }
}

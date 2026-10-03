<?php

namespace Tests\Feature;

use App\Jobs\ProcessChatTurnJob;
use App\Jobs\SummarizeConversationJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\ConversationMaintenanceService;
use App\Services\AI\Providers\FakeLlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatTurnRealGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_async_graph_completes_before_maintenance_and_replays_without_duplicates(): void
    {
        $this->setUpPassport();
        config([
            'ai.provider' => 'fake', 'ai.embedding_provider' => 'fake',
            'ai.web_search_enabled' => false, 'ai.openai_web_search_enabled' => false,
            // FakeLlmProvider deliberately returns generic test plans. This
            // transport scenario uses no clinical guidance or external corpus.
            'ai.require_retrieved_evidence' => false, 'ai.default_medical_sources' => [],
            'queue.default' => 'sync',
        ]);
        Http::preventStrayRequests();
        Bus::fake();
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'message_count' => 9]);
        $this->actingAs($user, 'user-api');
        $request = ['client_message_id' => 'real-graph-one', 'message' => 'How can I use the Rafiq app?', 'language' => 'en', 'async' => true];
        $url = "/api/v1/conversations/{$conversation->id}/chat";

        $this->mock(ConversationMaintenanceService::class, function (MockInterface $mock) use ($conversation): void {
            $mock->shouldReceive('dispatch')->once()->andReturnUsing(function (Conversation $actual, bool $alreadyResponded) use ($conversation): void {
                $turn = ChatTurn::where('conversation_id', $conversation->id)->sole();
                $this->assertTrue($alreadyResponded);
                $this->assertSame('completed', $turn->status);
                $this->assertNotNull($turn->assistant_message_id);
                $this->assertSame('completed', $turn->userMessage->metadata['delivery_status']);
                // Exercise the actual sync-after-response maintenance branch;
                // the bus fake captures work instead of making model calls.
                (new ConversationMaintenanceService())->dispatch($actual, $alreadyResponded);
            });
        });

        $this->postJson($url, $request)->assertStatus(202)->assertJsonPath('turn.status', 'queued');
        $job = null;
        Bus::assertDispatchedAfterResponse(ProcessChatTurnJob::class, function (ProcessChatTurnJob $dispatched) use (&$job): bool {
            $job = $dispatched;

            return true;
        });
        $this->assertInstanceOf(FakeLlmProvider::class, app(LlmProviderInterface::class));
        $job->handle(app(ChatTurnService::class), app(ChildChatService::class));

        $turn = ChatTurn::sole();
        $this->getJson($url.'/turns/real-graph-one')->assertOk()
            ->assertJsonPath('turn.status', 'completed')
            ->assertJsonPath('turn.message.content', 'This is a fake AI response for testing.')
            ->assertJsonPath('turn.message.client_message_id', 'real-graph-one');
        $this->assertSame($turn->user_message_id, Message::findOrFail($turn->assistant_message_id)->reply_to_message_id);
        Bus::assertDispatchedSync(SummarizeConversationJob::class, 1);
        Bus::assertNotDispatchedAfterResponse(SummarizeConversationJob::class);

        $this->postJson($url, $request)->assertOk()->assertJsonPath('turn.message.id', $turn->assistant_message_id);
        $job->handle(app(ChatTurnService::class), app(ChildChatService::class));
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('chat_turns', 1);
        $this->assertSame(10, $conversation->fresh()->message_count);
        Bus::assertDispatchedAfterResponse(ProcessChatTurnJob::class, 1);
        Bus::assertDispatchedSync(SummarizeConversationJob::class, 1);
        Http::assertNothingSent();
    }
}

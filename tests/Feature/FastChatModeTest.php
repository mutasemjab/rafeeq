<?php

namespace Tests\Feature;

use App\Jobs\ProcessChatTurnJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FastChatModeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        config(['ai.provider' => 'fake', 'ai.embedding_provider' => 'fake', 'queue.default' => 'database']);
        Http::preventStrayRequests();
        Bus::fake();
        $this->user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $this->conversation = Conversation::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user, 'user-api');
    }

    private function send(array $overrides = [])
    {
        return $this->postJson('/api/v1/conversations/'.$this->conversation->id.'/chat', array_merge([
            'client_message_id' => 'mode-test', 'message' => 'السلام عليكم', 'language' => 'ar', 'async' => true,
        ], $overrides));
    }

    private function subscribe(array $attributes = [], array $planAttributes = []): Subscription
    {
        $plan = Plan::factory()->create(array_merge(['type' => 'pro', 'is_active' => true], $planAttributes));
        return Subscription::create(array_merge(['user_id' => $this->user->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()], $attributes));
    }

    public function test_greeting_finishes_inline_without_model_or_worker_and_is_idempotent(): void
    {
        // Any model call would fail this test, including domain, planning and review.
        $this->app->instance(LlmProviderInterface::class, Mockery::mock(LlmProviderInterface::class));
        $this->send()->assertOk()->assertJsonPath('turn.status', 'completed')
            ->assertJsonPath('turn.response_mode', 'fast')->assertJsonPath('turn.message.response_type', 'social');
        $this->send()->assertOk()->assertJsonPath('turn.status', 'completed');
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('chat_turns', 1);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_mixed_greeting_and_question_does_not_bypass_normal_processing(): void
    {
        $this->send(['message' => 'السلام عليكم، ابني عنده مشكلة في الكلام'])->assertStatus(202)
            ->assertJsonPath('turn.status', 'queued');
        Bus::assertDispatched(ProcessChatTurnJob::class);
    }

    public function test_free_user_cannot_enable_extra_thinking_or_consume_quota_attempting_it(): void
    {
        $this->subscribe([], ['type' => 'free']);
        $this->send(['extra_thinking' => true])->assertForbidden()->assertJsonPath('error_code', 'EXTRA_THINKING_REQUIRES_PREMIUM');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('chat_turns', 0);
        $this->getJson('/api/v1/chat/usage')->assertOk()->assertJsonPath('can_use_extra_thinking', false);
    }

    /** @dataProvider invalidSubscriptions */
    public function test_expired_future_or_inactive_subscriptions_cannot_unlock_deep_mode(string $condition): void
    {
        $attributes = match ($condition) {
            'expired' => ['ends_at' => now()->subSecond()],
            'future' => ['starts_at' => now()->addDay()],
            'cancelled' => ['status' => 'canceled'],
            default => [],
        };
        $this->subscribe($attributes, $condition === 'inactive_plan' ? ['is_active' => false] : []);
        $this->send(['extra_thinking' => true])->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public static function invalidSubscriptions(): array
    {
        return [['expired'], ['future'], ['cancelled'], ['inactive_plan']];
    }

    public function test_premium_mode_is_persisted_and_cannot_change_on_replay(): void
    {
        $this->subscribe();
        $this->getJson('/api/v1/chat/usage')->assertOk()->assertJsonPath('can_use_extra_thinking', true)
            ->assertJsonPath('default_response_mode', 'fast');
        $this->send(['extra_thinking' => true])->assertOk()->assertJsonPath('turn.extra_thinking', true)
            ->assertJsonPath('turn.message.response_mode', 'deep');
        $this->send(['extra_thinking' => false])->assertStatus(409);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_worker_rechecks_subscription_before_using_a_paid_model(): void
    {
        $subscription = $this->subscribe();
        $this->send(['message' => 'Explain communication', 'extra_thinking' => true])->assertStatus(202);
        $subscription->update(['ends_at' => now()->subSecond()]);
        $chat = Mockery::mock(ChildChatService::class);
        $chat->shouldNotReceive('ask');
        $turn = ChatTurn::sole();
        app(ChatTurnService::class)->process($turn, $chat);
        $this->assertSame('EXTRA_THINKING_REQUIRES_PREMIUM', $turn->fresh()->error['code']);
        $this->assertFalse($turn->fresh()->error['retryable']);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_greeting_does_not_skip_consent_and_flag_requires_a_boolean(): void
    {
        $this->send(['extra_thinking' => 'deep'])->assertUnprocessable();
        $this->user->update(['ai_consent_accepted_at' => null]);
        $this->send()->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\{ChatTurn, Child, ChildDocument, Conversation, Specialist, User};
use App\Services\AI\{ChatTurnService, ChildChatService, SafetyTriageService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class FeedbackFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Bus::fake();
    }

    public function test_processing_reply_can_be_stopped_and_cannot_be_resurrected_by_a_worker(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $service = app(ChatTurnService::class);
        [$turn] = $service->accept($conversation, $user, 'stop-me', 'Please help', 'en');
        $turn->update(['status' => 'processing']);
        $this->actingAs($user, 'user-api')->deleteJson("/api/v1/conversations/{$conversation->id}/chat/turns/stop-me")
            ->assertOk()->assertJsonPath('turn.status', 'cancelled');
        $this->assertSame('cancelled', $turn->userMessage->fresh()->metadata['delivery_status']);
        $service->fail($turn, 'late_failure', 'Old provider call returned');
        $this->assertSame('cancelled', $turn->fresh()->status);
        $this->actingAs($user, 'user-api')->deleteJson("/api/v1/conversations/{$conversation->id}/chat/turns/stop-me")
            ->assertOk()->assertJsonPath('turn.status', 'cancelled');
        [$next] = $service->accept($conversation, $user, 'next-message', 'A new question', 'en');
        $this->assertSame('queued', $next->status);
    }

    public function test_stop_between_final_checkpoint_and_write_prevents_assistant_persistence(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $turns = app(ChatTurnService::class);
        [$turn] = $turns->accept($conversation, $user, 'race', 'Please help', 'en');
        $turn->update(['status' => 'processing']);
        $safety = Mockery::mock(SafetyTriageService::class);
        $safety->shouldReceive('evaluate')->once()->andReturn([
            'level' => 'emergency', 'reason_code' => 'test', 'source' => 'test', 'flags' => [],
        ]);
        $safety->shouldReceive('response')->once()->andReturn('A response');
        $this->app->instance(SafetyTriageService::class, $safety);
        try {
            app(ChildChatService::class)->ask($conversation, 'Please help', $user->id, null, 'en', $turn->userMessage,
                function ($stage) use ($turns, $turn): void {
                    if ($stage === 'persisting_reply') $turns->cancel($turn);
                });
            $this->fail('Cancelled work must not persist an assistant reply.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('no longer active', $error->getMessage());
        }
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame('cancelled', $turn->fresh()->status);
    }

    public function test_search_combines_name_and_language_independent_category_and_excludes_inactive_profiles(): void
    {
        $user = User::factory()->create();
        $speech = Specialist::factory()->create(['display_name' => 'منى أحمد', 'title' => 'أخصائية تخاطب', 'is_active' => true]);
        Specialist::factory()->create(['display_name' => 'منى أخرى', 'title' => 'علم النفس', 'is_active' => true]);
        Specialist::factory()->create(['display_name' => 'منى مخفية', 'title' => 'أخصائية تخاطب', 'is_active' => false]);
        $this->actingAs($user, 'user-api')->getJson('/api/v1/specialists?'.http_build_query(['search' => 'منى', 'specialization' => 'speech']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $speech->id);
        $this->getJson('/api/v1/specialists?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/specialists?search=unmatched')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_case_document_retry_requires_ownership_and_consent_and_skips_ready_files(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $doc = ChildDocument::create(['child_id' => $child->id, 'user_id' => $user->id,
            'original_name' => 'report.pdf', 'file_path' => 'reports/report.pdf', 'status' => 'failed']);
        $url = "/api/v1/children/{$child->id}/documents/{$doc->id}/retry";
        $this->actingAs(User::factory()->create(['ai_consent_accepted_at' => now()]), 'user-api')
            ->postJson($url)->assertForbidden();
        $this->actingAs($user, 'user-api')->postJson($url)->assertOk()->assertJsonPath('status', 'uploaded');
        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, 1);
        $doc->update(['status' => 'processed']);
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'processed');
        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, 1);
        $user->update(['ai_consent_accepted_at' => null]);
        $this->postJson($url)->assertStatus(403);
    }
}

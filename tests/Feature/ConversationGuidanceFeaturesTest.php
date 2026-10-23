<?php

namespace Tests\Feature;

use App\Models\{Child, Conversation, Message, User};
use App\Services\AI\{ChildContextService, ChildMemoryManager, ConversationGuidanceService, ConversationStateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ConversationGuidanceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Bus::fake();
    }

    public function test_free_form_answers_preserve_sources_unknowns_and_corrections_across_case_conversations(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id, 'age' => 9, 'birth_date' => null]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $text = 'He is nine. Crying started a week ago during homework. I do not know the hearing test result.';
        $message = Message::create(['conversation_id' => $conversation->id, 'child_id' => $child->id, 'user_id' => $user->id, 'role' => 'user', 'content' => $text]);
        $candidates = [];
        foreach ([['behavior.onset', 'a week ago', 'reported'], ['behavior.context', 'during homework', 'reported'], ['health.hearing_assessment', 'I do not know the hearing test result', 'unknown']] as [$key, $quote, $status]) {
            $candidates[] = ['key' => $key, 'type' => 'general', 'content' => $quote, 'evidence' => $quote, 'confidence' => 0.99, 'answer_status' => $status, 'fact_status' => 'confirmed_by_caregiver'];
        }
        app(ChildMemoryManager::class)->applyCandidates($child->id, $user->id, $message->id, $candidates);
        app(ConversationStateService::class)->recordPlan($conversation, ['action' => 'answer', 'memory_candidates' => $candidates], ['level' => 'routine'], $message->id, $text);
        $new = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $guidance = app(ConversationGuidanceService::class);
        $context = $guidance->context(app(ChildContextService::class)->build($child->id, $user->id, $new), [], 'speech_language', 'en');
        $this->assertSame('unknown', $context['known_answers']['health.hearing_assessment']['answer_status']);
        $this->assertSame($message->id, $context['known_answers']['behavior.context']['source_message_id']);
        $this->assertNotContains('health.hearing_assessment', array_column($context['candidate_questions'], 'key'));
        $this->assertNotContains('child.age', array_column($context['candidate_questions'], 'key'));

        $correction = Message::create(['conversation_id' => $new->id, 'child_id' => $child->id, 'user_id' => $user->id, 'role' => 'user', 'content' => 'It started yesterday, not a week ago.']);
        $corrected = array_replace($candidates[0], ['content' => 'started yesterday', 'evidence' => 'started yesterday']);
        app(ChildMemoryManager::class)->applyCandidates($child->id, $user->id, $correction->id, [$corrected]);
        $context = $guidance->context(app(ChildContextService::class)->build($child->id, $user->id, $new), $conversation->fresh()->case_state, 'behavior', 'en');
        $this->assertSame('started yesterday', $context['known_answers']['behavior.onset']['content']);
        $other = Child::factory()->create(['user_id' => $user->id]);
        $otherContext = $guidance->context(app(ChildContextService::class)->build($other->id, $user->id), [], 'communication', 'en');
        $this->assertArrayNotHasKey('behavior.onset', $otherContext['known_answers']);
    }

    public function test_declined_sensitive_history_is_not_turned_into_a_negative_finding_or_a_required_question(): void
    {
        $guidance = app(ConversationGuidanceService::class);
        $context = $guidance->context(['profile' => ['age' => 6]], ['fact_index' => [
            'health.pregnancy_history' => ['content' => 'I prefer not to discuss that', 'answer_status' => 'declined', 'source_message_id' => 3],
        ]], 'development', 'ar');
        $this->assertSame('declined', $context['known_answers']['health.pregnancy_history']['answer_status']);
        $this->assertNotContains('health.pregnancy_history', array_column($context['candidate_questions'], 'key'));
        $adult = $guidance->context(['profile' => ['age_months' => 300]], [], 'development', 'en');
        $this->assertFalse($adult['case_form_applicable']);
        $this->assertSame([], $adult['candidate_questions']);
    }

    public function test_route_priority_and_not_tried_are_distinct_from_failed_outcomes(): void
    {
        $service = app(ConversationGuidanceService::class);
        $this->assertSame('N04', $service->route(['action' => 'answer', 'intent' => 'another_activity'], ['level' => 'emergency'])['node_id']);
        $this->assertSame('N12', $service->route(['action' => 'refer_to_specialist', 'intent' => 'explanation'])['node_id']);
        $this->assertSame('N10', $service->route(['action' => 'answer', 'intent' => 'another_activity'])['node_id']);
        $conversation = Conversation::factory()->create(['user_id' => User::factory()]);
        app(ConversationStateService::class)->recordPlan($conversation, ['action' => 'answer', 'intent' => 'implementation_barrier'], ['level' => 'routine'], 4, 'We could not try it.');
        $this->assertSame('not_tried', data_get($conversation->fresh()->case_state, 'progress.status'));
        $this->assertSame([], data_get($conversation->case_state, 'progress.outcome_reports', []));
        app(ConversationStateService::class)->recordPlan($conversation, ['action' => 'answer', 'intent' => 'stop_plan'], ['level' => 'routine'], 5, 'Stop this plan.');
        $this->assertSame('stopped', data_get($conversation->fresh()->case_state, 'progress.status'));
        $this->assertFalse($conversation->case_state['follow_up_needed']);
    }

    public function test_feedback_is_scoped_and_idempotent_and_restorable_from_message_history(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $reply = Message::create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'role' => 'assistant', 'content' => 'Earlier reply']);
        $body = ['async' => true, 'client_message_id' => 'repair-one', 'message' => 'Please shorten that reply', 'language' => 'en',
            'feedback' => ['message_id' => $reply->id, 'reason' => 'too_long', 'comment' => 'One practical step please']];
        $url = "/api/v1/conversations/{$conversation->id}/chat";
        $this->actingAs($user, 'user-api')->postJson($url, $body)->assertStatus(202);
        $this->postJson($url, $body)->assertStatus(202);
        $stored = Message::where('role', 'user')->firstOrFail();
        $this->assertStringContainsString('One practical step please', $stored->content);
        $this->postJson($url, array_replace($body, ['message' => $stored->content]))->assertStatus(202);
        $this->assertDatabaseCount('chat_turns', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->assertSame('too_long', data_get($reply->fresh()->metadata, 'user_feedback.reason'));
        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertOk()->assertSee('feedback');
        $foreign = Message::create(['conversation_id' => Conversation::factory()->create(['user_id' => $user->id])->id, 'user_id' => $user->id, 'role' => 'assistant', 'content' => 'Other case']);
        $body['client_message_id'] = 'repair-other';
        $body['feedback']['message_id'] = $foreign->id;
        $this->postJson($url, $body)->assertNotFound();
        $body['feedback']['reason'] = 'disable_safety';
        $this->postJson($url, $body)->assertUnprocessable();
    }

    public function test_summary_uses_owned_reports_separates_proposals_and_does_not_share_or_invent_outcomes(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'case_state' => ['progress' => ['recommended_step' => 'An assistant proposal']]]);
        Message::create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'role' => 'user', 'content' => 'I would like help with homework.']);
        Message::create(['conversation_id' => Conversation::factory()->create(['user_id' => $user->id])->id, 'user_id' => $user->id, 'role' => 'user', 'content' => 'PRIVATE OTHER CHILD']);
        $url = "/api/v1/conversations/{$conversation->id}/summary-draft?language=en";
        $this->actingAs($user, 'user-api')->getJson($url)->assertOk()->assertJsonPath('shared', false)->assertJsonPath('review_required', true)
            ->assertSee('help with homework')->assertSee('does not establish that it was tried')->assertSee('No outcome was reported')->assertDontSee('PRIVATE OTHER CHILD');
        $this->actingAs(User::factory()->create(), 'user-api')->getJson($url)->assertForbidden();
        Bus::assertNothingDispatched();
    }
}

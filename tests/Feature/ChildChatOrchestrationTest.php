<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\ChildChatService;
use App\Services\AI\ChildContextService;
use App\Services\AI\ChildMemoryManager;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\ConversationStateService;
use App\Services\AI\DomainGuardService;
use App\Services\AI\FollowUpSuggestionService;
use App\Services\AI\SafetyTriageService;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ChildChatOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_emergency_short_circuits_scope_retrieval_and_answer_generation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();

        $dependencies['llm']->shouldReceive('chat')->never();
        $dependencies['llm']->shouldReceive('embeddingMany')->never();
        $dependencies['context']->shouldReceive('build')->never();
        $dependencies['knowledge']->shouldReceive('searchForCase')->never();
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->never();
        $dependencies['web']->shouldReceive('search')->never();
        $dependencies['guard']->shouldReceive('evaluate')->never();
        $dependencies['planner']->shouldReceive('plan')->never();
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn([
            'level' => 'emergency',
            'reason_code' => 'breathing_emergency',
            'reason' => 'Immediate danger.',
            'confidence' => 1.0,
            'flags' => ['breathing_emergency'],
            'source' => 'deterministic_rule',
            'model' => null,
        ]);
        $dependencies['safety']->shouldReceive('response')->once()->with('emergency', 'ar')->andReturn('اتصلي بالطوارئ المحلية الآن.');

        $reply = $this->service($dependencies)->ask(
            $conversation,
            'ابني لا يستطيع التنفس',
            $user->id,
            null,
            'ar'
        );

        $this->assertSame('urgent_escalation', $reply->metadata['response_type']);
        $this->assertSame('contact_local_emergency_services', $reply->metadata['next_action']);
        $this->assertContains('emergency', $reply->safety_flags);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_missing_information_returns_one_clarification_before_retrieval(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();

        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn([
            'profile' => ['age' => 5],
            'memories' => [],
            'summary' => null,
        ]);
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn([
            'action' => 'ask_clarification',
            'domain' => 'behavior',
            'case_specific' => true,
            'information_sufficient' => false,
            'reason' => 'ABC context is missing.',
            'question' => 'ماذا يحدث مباشرة قبل الصراخ، وماذا تفعلون بعده؟',
            'missing_fields' => ['antecedent', 'consequence'],
            'search_queries' => [],
            'follow_up_needed' => true,
            'confidence' => 0.95,
            'model' => 'planner-model',
        ]);
        $dependencies['llm']->shouldReceive('chat')->never();
        $dependencies['llm']->shouldReceive('embeddingMany')->never();
        $dependencies['knowledge']->shouldReceive('searchForCase')->never();
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->never();
        $dependencies['web']->shouldReceive('search')->never();

        $reply = $this->service($dependencies)->ask(
            $conversation,
            'ابني يصرخ كثيرًا، ماذا أفعل؟',
            $user->id,
            null,
            'ar'
        );

        $this->assertSame('clarification', $reply->metadata['response_type']);
        $this->assertSame(['antecedent', 'consequence'], $reply->metadata['turn_plan']['missing_fields']);
        $this->assertSame([], $reply->sources);
    }

    public function test_missing_retrieved_evidence_returns_explicit_insufficiency(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        Config::set('ai.openai_web_search_enabled', false);
        Config::set('ai.default_medical_sources', []);
        Config::set('ai.web_search_enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();

        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn([
            'profile' => ['age' => 5],
            'memories' => [],
            'summary' => null,
        ]);
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn($this->answerPlan());
        $dependencies['llm']->shouldReceive('embeddingMany')->once()->andReturn([[1.0, 0.0]]);
        $dependencies['llm']->shouldReceive('chat')->never();
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchForCase')->once()->andReturn([]);
        $dependencies['web']->shouldReceive('search')->never();

        $reply = $this->service($dependencies)->ask(
            $conversation,
            'ما الخطة المناسبة للطفل؟',
            $user->id,
            null,
            'ar'
        );

        $this->assertSame('insufficient_evidence', $reply->metadata['response_type']);
        $this->assertContains('insufficient_evidence', $reply->safety_flags);
        $this->assertStringContainsString('لن أخمّن', $reply->content);
    }

    public function test_answer_persists_child_fact_case_state_and_one_next_question(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        Config::set('ai.openai_web_search_enabled', false);
        Config::set('ai.default_medical_sources', []);
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'child_id' => $child->id,
        ]);
        $dependencies = $this->baseDependencies();

        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn([
            'profile' => ['id' => $child->id, 'age_months' => 60],
            'memories' => [],
            'summary' => null,
        ]);
        $plan = $this->answerPlan();
        $plan['memory_candidates'] = [[
            'key' => 'communication.primary_language',
            'type' => 'language',
            'title' => 'Primary language',
            'content' => 'The child primarily speaks Arabic.',
            'confidence' => 0.98,
            'evidence' => 'لغته الأساسية العربية',
            'fact_status' => 'confirmed_by_caregiver',
        ]];
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn($plan);
        $dependencies['llm']->shouldReceive('embeddingMany')->once()->andReturn([[1.0, 0.0]]);
        $dependencies['llm']->shouldReceive('answer')->once()->andReturn([
            'content' => 'ابدئي بخطوة بسيطة ومحددة.',
            'sources' => [],
            'model' => 'answer-model',
            'used_web_search' => false,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 8],
        ]);
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchForCase')->once()->andReturn([[
            'source_label' => 'KB_SOURCE_1',
            'source_type' => 'knowledge_base',
            'chunk_id' => 1,
            'content' => 'Use one observable first step.',
            'similarity' => 0.9,
        ]]);
        $dependencies['web']->shouldReceive('search')->never();
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn([
            'question' => 'بعد ثلاثة أيام، ما التغيير الذي لاحظتِه؟',
            'purpose' => 'outcome_check',
            'wait_for_observation' => true,
        ]);

        $reply = $this->service($dependencies)->ask(
            $conversation,
            'لغته الأساسية العربية وأريد مساعدته بخطوة واحدة',
            $user->id,
            $child->id,
            'ar'
        );

        $this->assertStringEndsWith('بعد ثلاثة أيام، ما التغيير الذي لاحظتِه؟', $reply->content);
        $this->assertSame(['بعد ثلاثة أيام، ما التغيير الذي لاحظتِه؟'], $reply->metadata['suggested_questions']);
        $this->assertSame('behavior', $conversation->fresh()->active_domain);
        $this->assertSame('بعد ثلاثة أيام، ما التغيير الذي لاحظتِه؟', $conversation->fresh()->next_question);
        $this->assertDatabaseHas('child_memories', [
            'child_id' => $child->id,
            'memory_key' => 'communication.primary_language',
        ]);
    }

    private function baseDependencies(): array
    {
        return [
            'llm' => Mockery::mock(LlmProviderInterface::class),
            'context' => Mockery::mock(ChildContextService::class),
            'knowledge' => Mockery::mock(KnowledgeSearchService::class),
            'attachments' => Mockery::mock(ChatAttachmentSearchService::class),
            'web' => Mockery::mock(WebSearchServiceInterface::class),
            'guard' => Mockery::mock(DomainGuardService::class),
            'safety' => Mockery::mock(SafetyTriageService::class),
            'planner' => Mockery::mock(ChatTurnPlannerService::class),
            'memory' => new ChildMemoryManager(),
            'state' => new ConversationStateService(),
            'follow_up' => Mockery::mock(FollowUpSuggestionService::class),
        ];
    }

    private function service(array $dependencies): ChildChatService
    {
        return new ChildChatService(
            $dependencies['llm'],
            $dependencies['context'],
            $dependencies['knowledge'],
            $dependencies['attachments'],
            $dependencies['web'],
            $dependencies['guard'],
            $dependencies['safety'],
            $dependencies['planner'],
            $dependencies['memory'],
            $dependencies['state'],
            $dependencies['follow_up']
        );
    }

    private function routineSafety(): array
    {
        return [
            'level' => 'routine',
            'reason_code' => 'no_safety_cue',
            'reason' => 'No safety cue.',
            'confidence' => 1.0,
            'flags' => [],
            'source' => 'deterministic_rule',
            'model' => null,
        ];
    }

    private function allowedDomain(): array
    {
        return [
            'allowed' => true,
            'confidence' => 0.99,
            'category' => 'behavior',
            'reason' => 'Within scope.',
            'search_queries' => [],
            'model' => 'guard-model',
        ];
    }

    private function answerPlan(): array
    {
        return [
            'action' => 'answer',
            'domain' => 'behavior',
            'case_specific' => true,
            'information_sufficient' => true,
            'reason' => 'Enough information.',
            'question' => null,
            'missing_fields' => [],
            'search_queries' => [],
            'follow_up_needed' => true,
            'risk_level' => 'moderate',
            'evidence_required' => true,
            'web_search_needed' => false,
            'memory_candidates' => [],
            'confidence' => 0.9,
            'model' => 'planner-model',
        ];
    }
}

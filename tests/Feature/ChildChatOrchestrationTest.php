<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\CaseDocumentContextService;
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
use Illuminate\Support\Facades\Bus;
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
            'question' => 'ذكرتِ أن الصراخ يتكرر؛ ما الذي يحدث قبله مباشرة؟',
            'known_facts' => ['عمر الطفل خمس سنوات', 'الصراخ متكرر'],
            'decision_to_make' => 'اختيار أول تدخل وقائي.',
            'question_target' => 'antecedent',
            'question_anchor' => 'الصراخ المتكرر',
            'expected_answer_use' => 'تحديد التغيير الوقائي الأنسب.',
            'missing_fields' => ['antecedent'],
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
        $this->assertSame(['antecedent'], $reply->metadata['turn_plan']['missing_fields']);
        $this->assertSame([], $reply->sources);
        $state = $conversation->fresh()->case_state;
        $this->assertSame(['عمر الطفل خمس سنوات', 'الصراخ متكرر'], $state['known_facts']);
        $this->assertSame('antecedent', data_get($state, 'asked_questions.0.target'));
        $this->assertSame('clarification', data_get($state, 'asked_questions.0.source'));
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
        $dependencies['knowledge']->shouldReceive('searchDeeper')->once()->andReturn([]);
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
        Config::set('ai.openai_web_search_enabled', true);
        Config::set('ai.max_provider_web_sources', 6);
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
        $providerSources = collect(range(1, 10))->map(fn (int $index): array => [
            'title' => "Web source {$index}",
            'url' => "https://example.org/source-{$index}",
            'snippet' => "Evidence {$index}",
        ])->all();
        $dependencies['llm']->shouldReceive('answer')->once()->withArgs(
            fn (array $messages, array $options): bool => ($options['web_search'] ?? false) === true
                && ($options['web_search_required'] ?? false) === true
        )->andReturn([
            'content' => 'ابدئي بخطوة بسيطة ومحددة [KB_SOURCE_1] [WEB_SOURCE_1].',
            'sources' => $providerSources,
            'model' => 'answer-model',
            'used_web_search' => true,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 8],
        ]);
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchForCase')->once()->andReturn([[
            'source_label' => 'KB_SOURCE_1',
            'source_type' => 'knowledge_base',
            'chunk_id' => 1,
            'content' => 'Use one observable first step.',
            'similarity' => 0.55,
        ]]);
        $dependencies['knowledge']->shouldReceive('searchDeeper')->once()->andReturnUsing(fn ($e, $q, $f, $sources) => $sources);
        $dependencies['llm']->shouldReceive('chatJson')->twice()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_knowledge_coverage')
            ->andReturn(['sufficient' => false, 'supporting_quotes' => [], 'missing_information' => ['Specific supporting guidance is absent.'], 'search_queries' => []]);
        $dependencies['web']->shouldReceive('search')->never();
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn([
            'question' => 'بعد ثلاثة أيام، ما التغيير الذي لاحظتِه؟',
            'purpose' => 'outcome_check',
            'wait_for_observation' => true,
            'anchor' => 'الخطوة البسيطة لمدة ثلاثة أيام',
            'decision_impact' => 'تحديد تثبيت الخطوة أو تعديلها.',
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
        $this->assertSame(
            'الخطوة البسيطة لمدة ثلاثة أيام',
            data_get($conversation->fresh()->case_state, 'asked_questions.0.anchor')
        );
        $this->assertSame(6, $reply->metadata['evidence']['web_sources']);
        $this->assertTrue($reply->metadata['evidence']['used_web_search']);
        $this->assertTrue($reply->metadata['evidence']['internal_search_performed']);
        $this->assertTrue($reply->metadata['evidence']['internal_search_completed_before_web']);
        $this->assertTrue($reply->metadata['evidence']['hosted_web_search_requested']);
        $this->assertSame(
            ['internal_knowledge', 'hosted_web'],
            $reply->metadata['evidence']['search_order']
        );
        $this->assertCount(1, collect($reply->sources)->where('source_type', 'web'));
        $this->assertDatabaseHas('child_memories', [
            'child_id' => $child->id,
            'memory_key' => 'communication.primary_language',
        ]);
    }

    public function test_nonurgent_referral_uses_case_context_and_generates_helpful_response_without_medical_evidence(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        Config::set('ai.openai_web_search_enabled', false);
        Config::set('ai.web_search_enabled', false);
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id, 'diagnosis' => 'Reported autism']);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $dependencies = $this->baseDependencies();
        $documents = ['attachments' => [['status' => 'processed', 'excerpts' => ['Reported diagnosis: autism; age: 3.']]], 'child_documents' => []];
        $caseDocuments = Mockery::mock(CaseDocumentContextService::class);
        $caseDocuments->shouldReceive('build')->once()->with($user->id, Mockery::type(Conversation::class), $child->id)->andReturn($documents);
        $this->app->instance(CaseDocumentContextService::class, $caseDocuments);
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => ['age' => 3, 'diagnosis' => 'Reported autism'], 'memories' => []]);
        $plan = array_merge($this->answerPlan(), ['action' => 'refer_to_specialist', 'information_sufficient' => false, 'follow_up_needed' => false]);
        $dependencies['planner']->shouldReceive('plan')->once()->withArgs(
            fn ($message, $context): bool => $context['documents'] === $documents
        )->andReturn($plan);
        $dependencies['llm']->shouldReceive('embeddingMany')->once()->andReturn([[1.0, 0.0]]);
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchForCase')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchDeeper')->once()->andReturn([]);
        $dependencies['llm']->shouldReceive('answer')->once()->withArgs(function (array $messages): bool {
            return str_contains($messages[0]['content'], 'NON-URGENT professional assessment')
                && str_contains($messages[1]['content'], 'Reported diagnosis: autism; age: 3.');
        })->andReturn([
            'content' => 'التوحد مذكور في البيانات التي أرفقتها. لا أقدر أحدد تشخيصًا جديدًا من الشات، لكن أقدر أساعدك في ترتيب الملاحظات التي تريد مناقشتها مع المختص.',
            'sources' => [], 'model' => 'answer-model',
        ]);
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn(['question' => null]);
        $reply = $this->service($dependencies)->ask($conversation, 'شو تشخيص الحالة المرفقة؟', $user->id, $child->id, 'ar');

        $this->assertSame('specialist_referral', $reply->metadata['response_type']);
        $this->assertSame('arrange_professional_assessment', $reply->metadata['next_action']);
        $this->assertSame('refer_to_specialist', $reply->metadata['turn_plan']['action']);
        $this->assertSame('refer_to_specialist', $conversation->fresh()->case_state['last_action']);
        $this->assertStringContainsString('التوحد مذكور', $reply->content);
        $this->assertNotSame(config('ai.safety_messages.specialist_referral.ar'), $reply->content);
        $this->assertSame([], $reply->sources);
    }

    public function test_chat_frustration_can_receive_natural_reply_without_embedding_or_medical_search(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn(array_merge($this->allowedDomain(), ['category' => 'app_support']));
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => null, 'memories' => []]);
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn(array_merge($this->answerPlan(), [
            'domain' => 'app_support', 'case_specific' => false, 'risk_level' => 'low',
            'evidence_required' => false, 'follow_up_needed' => false,
        ]));
        $dependencies['llm']->shouldReceive('embeddingMany')->never();
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->never();
        $dependencies['knowledge']->shouldReceive('searchForCase')->never();
        $dependencies['web']->shouldReceive('search')->never();
        $dependencies['llm']->shouldReceive('answer')->once()->withArgs(
            fn (array $messages, array $options): bool => $options['web_search'] === false
        )->andReturn(['content' => 'معك حق، كررت الإحالة بدل ما أوضح كيف أقدر أساعدك. أقدر أساعدك تفهم المعلومات اللي شاركتها وترتب أسئلتك.', 'sources' => []]);
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn(['question' => null]);
        $reply = $this->service($dependencies)->ask($conversation, 'حتى في مجال تجاوبني على أي إشي؟', $user->id, null, 'ar');

        $this->assertSame('answer', $reply->metadata['response_type']);
        $this->assertFalse($reply->metadata['evidence']['internal_search_performed']);
        $this->assertSame([], $reply->metadata['evidence']['search_order']);
        $this->assertSame(0, $reply->metadata['retrieval_query_count']);
        $this->assertSame([], $reply->sources);
    }

    public function test_readable_case_report_can_be_summarized_without_clinical_retrieval(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();
        $documents = ['attachments' => [['content_available' => true, 'excerpts' => [['content' => 'Report states: uses gestures to request.']]]], 'child_documents' => []];
        $caseDocuments = Mockery::mock(CaseDocumentContextService::class);
        $caseDocuments->shouldReceive('build')->once()->andReturn($documents);
        $this->app->instance(CaseDocumentContextService::class, $caseDocuments);
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => null, 'memories' => []]);
        $dependencies['planner']->shouldReceive('plan')->once()->withArgs(
            fn ($message, $context): bool => $context['documents'] === $documents
        )->andReturn(array_merge($this->answerPlan(), ['evidence_required' => false, 'follow_up_needed' => false]));
        $dependencies['llm']->shouldReceive('embeddingMany')->never();
        $dependencies['knowledge']->shouldReceive('searchForCase')->never();
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->never();
        $dependencies['llm']->shouldReceive('answer')->once()->withArgs(
            fn (array $messages, array $options): bool => str_contains($messages[1]['content'], 'uses gestures to request') && $options['web_search'] === false
        )->andReturn(['content' => 'التقرير المرفق بيذكر إنه بيستخدم الإشارة عشان يطلب الأشياء.', 'sources' => []]);
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn(['question' => null]);
        $reply = $this->service($dependencies)->ask($conversation, 'لخص لي الملاحظة المكتوبة في التقرير فقط', $user->id, null, 'ar');

        $this->assertSame('answer', $reply->metadata['response_type']);
        $this->assertStringContainsString('التقرير المرفق', $reply->content);
        $this->assertSame([], $reply->sources);
        $this->assertFalse($reply->metadata['evidence']['internal_search_performed']);
    }

    public function test_private_case_attachment_is_not_sufficient_authority_for_clinical_guidance(): void
    {
        Config::set('ai.require_retrieved_evidence', true);
        Config::set('ai.openai_web_search_enabled', false);
        Config::set('ai.web_search_enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $dependencies = $this->baseDependencies();
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => null, 'memories' => []]);
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn($this->answerPlan());
        $dependencies['llm']->shouldReceive('embeddingMany')->once()->andReturn([[1.0, 0.0]]);
        $dependencies['attachments']->shouldReceive('searchWithEmbeddings')->once()->andReturn([[
            'source_label' => 'CHAT_SOURCE_1', 'source_type' => 'chat_attachment',
            'content' => 'Caregiver report: child hits during transitions.',
        ]]);
        $dependencies['knowledge']->shouldReceive('searchForCase')->once()->andReturn([]);
        $dependencies['knowledge']->shouldReceive('searchDeeper')->once()->andReturn([]);
        $dependencies['llm']->shouldReceive('answer')->never();
        $reply = $this->service($dependencies)->ask($conversation, 'ما الخطة العلاجية بناء على الملف؟', $user->id, null, 'ar');

        $this->assertSame('insufficient_evidence', $reply->metadata['response_type']);
    }

    public function test_memory_and_summary_work_is_deferred_when_queue_is_synchronous(): void
    {
        Bus::fake();
        Config::set('queue.default', 'sync');
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id, 'message_count' => 9]);
        $dependencies = $this->baseDependencies();
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => null, 'memories' => []]);
        $dependencies['planner']->shouldReceive('plan')->once()->andReturn(array_merge($this->answerPlan(), ['evidence_required' => false, 'follow_up_needed' => false]));
        $dependencies['llm']->shouldReceive('answer')->once()->andReturn(['content' => 'أقدر أساعدك في استخدام ملف الطفل.', 'sources' => []]);
        $dependencies['follow_up']->shouldReceive('suggest')->once()->andReturn(['question' => null]);
        $this->service($dependencies)->ask($conversation, 'كيف أستخدم الملف؟', $user->id, $child->id, 'ar');

        Bus::assertDispatchedAfterResponse(\App\Jobs\UpdateChildMemoryJob::class);
        Bus::assertDispatchedAfterResponse(\App\Jobs\SummarizeConversationJob::class);
        Bus::assertNotDispatchedSync(\App\Jobs\UpdateChildMemoryJob::class);
        Bus::assertNotDispatchedSync(\App\Jobs\SummarizeConversationJob::class);
    }

    public function test_saved_case_and_readable_report_reach_planner_on_every_turn_without_repeating_child_id(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id, 'age' => 4, 'birth_date' => null]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        \App\Models\ChildDocument::create([
            'child_id' => $child->id, 'user_id' => $user->id,
            'original_name' => 'report.pdf', 'file_path' => 'reports/report.pdf', 'status' => 'processed',
            'metadata' => ['extracted_text' => 'Caregiver reports that the child uses short phrases.'],
        ]);
        $dependencies = $this->baseDependencies();
        $dependencies['context'] = new ChildContextService();
        $dependencies['safety']->shouldReceive('evaluate')->twice()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->twice()->andReturn($this->allowedDomain());
        $ages = [];
        $dependencies['planner']->shouldReceive('plan')->twice()->andReturnUsing(function ($message, $context) use ($child, &$ages): array {
            $this->assertSame($child->id, $context['profile']['id']);
            $ages[] = $context['profile']['age'];
            $this->assertSame('processed', data_get($context, 'documents.child_documents.0.status'));
            $this->assertStringContainsString('short phrases', data_get($context, 'documents.child_documents.0.excerpts.0.content'));

            return array_merge($this->answerPlan(), [
                'action' => 'ask_clarification', 'question' => 'Which daily activity would you like help with?',
            ]);
        });
        $service = $this->service($dependencies);
        $first = $service->ask($conversation, 'Help with the saved case', $user->id);
        $child->update(['age' => 5]);
        $second = $service->ask($conversation, 'I updated the case', $user->id);
        $this->assertSame([4, 5], $ages);
        $this->assertSame($child->id, $first->child_id);
        $this->assertSame($child->id, $second->child_id);
    }

    public function test_case_load_failure_preserves_retryable_turn_without_generating_an_unscoped_answer(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $dependencies = $this->baseDependencies();
        $dependencies['safety']->shouldReceive('evaluate')->once()->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andThrow(new \RuntimeException('Temporary case lookup failure'));
        $dependencies['planner']->shouldNotReceive('plan');
        $dependencies['llm']->shouldNotReceive('chat');
        $turns = app(\App\Services\AI\ChatTurnService::class);
        [$turn] = $turns->accept($conversation, $user, 'case-lookup', 'ساعدني مع الحالة', 'ar');
        $turns->process($turn, $this->service($dependencies));
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertSame('case_context', $turn->error['stage']);
        $this->assertStringContainsString('بيانات الحالة', $turn->error['message']);
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame('ساعدني مع الحالة', $turn->userMessage->content);
    }

    public function test_feedback_on_an_old_reply_reaches_guards_and_planner_with_its_original_question(): void
    {
        Config::set('ai.recent_messages_limit', 2);
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $original = $conversation->messages()->create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Help with homework frustration']);
        $target = $conversation->messages()->create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'An earlier plan', 'reply_to_message_id' => $original->id]);
        $conversation->messages()->create(['user_id' => $user->id, 'role' => 'user', 'content' => 'A later topic']);
        $conversation->messages()->create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'A later reply']);
        $dependencies = $this->baseDependencies();
        $dependencies['safety']->shouldReceive('evaluate')->once()->withArgs(function ($message, $history, $feedback) use ($target) {
            $this->assertStringContainsString('We only have five minutes', $message);
            $this->assertSame($target->id, $feedback['message_id']);
            $this->assertSame('Help with homework frustration', $feedback['original_question']);
            return true;
        })->andReturn($this->routineSafety());
        $dependencies['guard']->shouldReceive('evaluate')->once()->withArgs(function ($message, $history) {
            $this->assertStringContainsString('homework frustration', $history[count($history) - 2]['content']);
            return true;
        })->andReturn($this->allowedDomain());
        $dependencies['context']->shouldReceive('build')->once()->andReturn(['profile' => null, 'memories' => [], 'summary' => null]);
        $dependencies['planner']->shouldReceive('plan')->once()->withArgs(function ($message, $context) {
            $this->assertSame('An earlier plan', $context['reply_feedback']['original_reply']);
            $this->assertSame('impractical', $context['reply_feedback']['reason']);
            return true;
        })->andReturn(array_replace($this->answerPlan(), ['action' => 'ask_clarification', 'question' => 'Which part takes too long?']));
        $dependencies['llm']->shouldNotReceive('chat');
        $turns = app(\App\Services\AI\ChatTurnService::class);
        [$turn] = $turns->accept($conversation, $user, 'old-repair', 'Please adjust that reply', 'en', [
            'message_id' => $target->id, 'reason' => 'impractical', 'comment' => 'We only have five minutes',
        ]);
        $turns->process($turn, $this->service($dependencies));
        $this->assertSame('completed', $turn->fresh()->status);
    }

    public function test_a_new_emergency_in_the_feedback_comment_short_circuits_repair(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $target = $conversation->messages()->create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'Earlier routine reply']);
        $dependencies = $this->baseDependencies();
        $dependencies['safety'] = new SafetyTriageService($dependencies['llm']);
        $dependencies['planner']->shouldNotReceive('plan');
        $dependencies['guard']->shouldNotReceive('evaluate');
        $dependencies['llm']->shouldNotReceive('chatJson');
        $turns = app(\App\Services\AI\ChatTurnService::class);
        [$turn] = $turns->accept($conversation, $user, 'urgent-repair', 'Please correct the reply', 'en', [
            'message_id' => $target->id, 'reason' => 'misunderstood', 'comment' => 'My child cannot breathe now',
        ]);
        $turns->process($turn, $this->service($dependencies));
        $this->assertSame('completed', $turn->fresh()->status);
        $this->assertSame('urgent_escalation', $turn->assistantMessage->metadata['response_type']);
        $this->assertSame('N04', $turn->assistantMessage->metadata['dialogue_route']['node_id']);
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

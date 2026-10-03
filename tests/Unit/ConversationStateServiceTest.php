<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ConversationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_case_facts_and_question_history_without_exact_duplicates(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id' => User::factory(),
            'case_state' => [
                'known_facts' => ['عمر الطفل خمس سنوات'],
                'asked_questions' => [[
                    'question' => 'كم مرة يحدث الصراخ يوميًا؟',
                    'target' => 'frequency',
                    'source' => 'clarification',
                ]],
            ],
        ]);
        $service = new ConversationStateService();

        $service->recordPlan($conversation, [
            'action' => 'ask_clarification',
            'domain' => 'behavior',
            'case_specific' => true,
            'risk_level' => 'low',
            'known_facts' => ['عمر الطفل خمس سنوات', 'الصراخ يحدث عند إيقاف الجهاز'],
            'missing_fields' => ['consequence'],
            'decision_to_make' => 'اختيار بديل مناسب للانتقال.',
            'question' => 'بعد إيقاف الجهاز وبدء الصراخ، ماذا يحدث بعد ذلك مباشرة؟',
            'question_target' => 'consequence',
            'question_anchor' => 'إيقاف الجهاز',
            'expected_answer_use' => 'معرفة ما إذا كانت الاستجابة الحالية تقوي الصراخ.',
            'follow_up_needed' => true,
        ], ['level' => 'routine']);

        $service->recordAnswer($conversation->fresh(), 'كم مرة يحدث الصراخ يوميًا؟', [
            'purpose' => 'outcome_check',
            'anchor' => 'الصراخ اليومي',
            'decision_impact' => 'تحديد شدة الخطة.',
        ]);

        $state = $conversation->fresh()->case_state;

        $this->assertSame([
            'عمر الطفل خمس سنوات',
            'الصراخ يحدث عند إيقاف الجهاز',
        ], $state['known_facts']);
        $this->assertCount(2, $state['asked_questions']);
        $this->assertSame('consequence', data_get($state, 'asked_questions.1.target'));
        $this->assertSame('إيقاف الجهاز', data_get($state, 'asked_questions.1.anchor'));
    }

    public function test_an_explicit_correction_supersedes_the_same_field_and_retains_its_provenance(): void
    {
        $conversation = Conversation::factory()->create(['user_id' => User::factory()]);
        $service = new ConversationStateService();
        foreach ([['age_years', 'عمره ثلاث سنوات', 21], ['child.age', 'أقصد عمره خمس سنوات', 22]] as [$key, $content, $id]) {
            $service->recordPlan($conversation->fresh(), [
                'action' => 'answer', 'known_facts' => [$content],
                'memory_candidates' => [[
                    'key' => $key, 'content' => $content, 'evidence' => $content,
                    'confidence' => 0.98, 'fact_status' => 'confirmed_by_caregiver',
                ]],
            ], ['level' => 'routine'], $id, $content);
        }
        $state = $conversation->fresh()->case_state;
        $this->assertSame(['أقصد عمره خمس سنوات'], $state['known_facts']);
        $this->assertSame(22, $state['fact_index']['child.age']['source_message_id']);
        $this->assertSame('superseded', $state['superseded_facts'][0]['status']);
        $this->assertSame('عمره ثلاث سنوات', $state['superseded_facts'][0]['content']);
        $service->recordPlan($conversation->fresh(), [
            'action' => 'answer', 'known_facts' => ['عمره ثلاث سنوات'],
            'memory_candidates' => [['key' => 'child.age', 'content' => 'عمره ثلاث سنوات', 'evidence' => 'عمره ثلاث سنوات', 'confidence' => 0.98]],
        ], ['level' => 'routine'], 24, 'راجعت التاريخ الصحيح، عمره ثلاث سنوات');
        $this->assertSame(['عمره ثلاث سنوات'], $conversation->fresh()->case_state['known_facts']);
    }

    public function test_progress_preserves_recommendation_and_report_without_claiming_adherence(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id' => User::factory(),
            'case_state' => ['case_specific' => true, 'decision_to_make' => 'دعم الانتقال', 'question_scope' => 'behavior:transition'],
        ]);
        $service = new ConversationStateService();
        $service->recordAnswer($conversation, 'بعد التجربة، كيف سار الانتقال؟', ['wait_for_observation' => true], 'answer', 'يمكن تجربة تنبيه قبل الانتقال.');
        $this->assertSame('awaiting_observation', $conversation->fresh()->case_state['progress']['status']);
        $this->assertSame('observation', $conversation->fresh()->case_state['progress']['waiting_for']);
        $service->recordPlan($conversation->fresh(), ['action' => 'answer', 'outcome_reported' => true], ['level' => 'routine'], 23, 'جربنا ومفيش فرق.');
        $state = $conversation->fresh()->case_state;
        $this->assertSame('جربنا ومفيش فرق.', $state['progress']['outcome_reports'][0]['content']);
        $this->assertSame('يمكن تجربة تنبيه قبل الانتقال.', $state['progress']['recommended_step']);
        $this->assertSame(1, $state['observation_round']);
    }
}

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
}

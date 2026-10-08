<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\FollowUpSuggestionService;
use Mockery;
use Tests\TestCase;

class FollowUpSuggestionServiceTest extends TestCase
{
    public function test_reviewed_follow_up_needs_no_extra_model_call_and_rejects_assumed_gender(): void
    {
        $llm = \Mockery::mock(\App\Services\AI\Contracts\LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();
        $service = new \App\Services\AI\FollowUpSuggestionService($llm);
        $result = ['question'=>'بعد ما تجرّبي الخطوة، هل ظهرت كلمة؟','anchor'=>'الخطوة','decision_impact'=>'مراجعة الخطوة','wait_for_observation'=>true];
        $this->assertNull($service->validateResult($result, [], [], [])['question']);
        $result['question']='هل استخدم كلمة من نفسه؟';
        $result['wait_for_observation']=false;
        $this->assertSame($result['question'], $service->validateResult($result, [], [], ['outcome_reported'=>true])['question']);
        $result['wait_for_observation']=true;
        $this->assertNull($service->validateResult($result, [], [], ['outcome_reported'=>true])['question']);
    }
    public function test_it_returns_one_structured_next_question_in_the_requested_language(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')
            ->once()
            ->withArgs(function (array $messages, array $schema, array $options): bool {
                $payload = (string) data_get($messages, '1.content');

                return ($schema['properties']['question']['type'] ?? null) === ['string', 'null']
                    && in_array('decision_impact', $schema['required'] ?? [], true)
                    && ($options['schema_name'] ?? null) === 'rafeeq_follow_up'
                    && str_contains($payload, '"response_language":"ar"')
                    && str_contains($payload, '"asked_questions"');
            })
            ->andReturn([
                'question' => 'بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟',
                'purpose' => 'outcome_check',
                'wait_for_observation' => true,
                'anchor' => 'تطبيق الخطوة ثلاثة أيام',
                'decision_impact' => 'تحديد ما إذا كنا سنثبت الخطوة أو نعدّلها.',
            ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'ابني يصرخ كثيرًا',
            'ابدئي بتسجيل الموقف.',
            ['domain' => 'behavior'],
            ['profile' => ['age' => 5], 'memories' => []],
            [],
            'ar',
            [
                'asked_questions' => [[
                    'question' => 'ماذا يحدث قبل الصراخ؟',
                    'target' => 'antecedent',
                ]],
            ]
        );

        $this->assertSame('بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟', $result['question']);
        $this->assertTrue($result['wait_for_observation']);
        $this->assertSame('after_observation', $result['response_timing']);
        $this->assertSame('تطبيق الخطوة ثلاثة أيام', $result['anchor']);
        $this->assertStringContainsString('نعدّلها', $result['decision_impact']);
    }

    public function test_it_keeps_one_atomic_measurement_without_another_model_call(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(
            [
                'question' => 'بعد استخدام المؤقت، كم استمر الصراخ، وهل انتقل للنشاط التالي؟',
                'purpose' => 'outcome_check',
                'wait_for_observation' => true,
                'anchor' => 'استخدام المؤقت',
                'decision_impact' => 'تعديل الخطة.',
            ]
        );

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'يصرخ عند إيقاف الآيباد.',
            'استخدمي مؤقتًا قبل الانتقال.',
            ['domain' => 'behavior'],
            ['profile' => ['age' => 5], 'memories' => []],
            [],
            'ar'
        );

        $this->assertSame('بعد استخدام المؤقت، كم استمر الصراخ؟', $result['question']);
        $this->assertStringNotContainsString('وهل', $result['question']);
    }

    public function test_it_skips_optional_generation_when_planner_needs_no_follow_up(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'شكرًا', 'أهلًا بك.', ['follow_up_needed' => false], [], [], 'ar'
        );

        $this->assertNull($result['question']);
        $this->assertFalse($result['wait_for_observation']);
    }

    public function test_it_drops_a_question_repeated_after_atomic_truncation(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'question' => 'أين حدث الصراخ، وكم استمر؟',
            'purpose' => 'setting_check',
            'wait_for_observation' => false,
            'anchor' => 'الصراخ',
            'decision_impact' => 'اختيار الخطوة حسب المكان.',
        ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'في البيت', 'فهمت المكان.', [], [], [], 'ar',
            ['asked_questions' => [['question' => 'اين حدث الصراخ؟']]]
        );

        $this->assertNull($result['question']);
        $this->assertNull($result['decision_impact']);
    }

    public function test_it_preserves_a_single_question_with_an_embedded_time_clause(): void
    {
        $question = 'How did he respond when the timer rang?';
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'question' => $question,
            'purpose' => 'outcome_check',
            'wait_for_observation' => true,
            'anchor' => 'The timer',
            'decision_impact' => 'Adjust the transition cue.',
        ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'He screams at transitions.', 'Use a timer.', [], [], [], 'en'
        );

        $this->assertSame($question, $result['question']);
    }

    public function test_it_drops_questions_without_a_decision_impact(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'question' => 'How long did the transition take?',
            'purpose' => 'outcome_check',
            'wait_for_observation' => true,
            'anchor' => 'The transition',
            'decision_impact' => null,
        ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'Thanks.', 'You are welcome.', [], [], [], 'en'
        );

        $this->assertNull($result['question']);
        $this->assertFalse($result['wait_for_observation']);
    }

    public function test_it_drops_a_question_already_in_recent_assistant_history(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'question' => 'What happened just before the screaming?',
            'purpose' => 'antecedent',
            'wait_for_observation' => false,
            'anchor' => 'Screaming',
            'decision_impact' => 'Identify a trigger.',
        ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'I turned the screen off.', 'We can plan the transition.', [], [],
            [['role' => 'assistant', 'content' => 'Let us understand the situation. What happened just before the screaming?']],
            'en'
        );

        $this->assertNull($result['question']);
    }

    public function test_a_previous_round_does_not_block_a_new_outcome_measurement(): void
    {
        $question = 'How long did the transition take?';
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'question' => $question, 'purpose' => 'outcome_check', 'wait_for_observation' => true,
            'anchor' => 'The next transition', 'decision_impact' => 'Adjust the step based on the next result.',
        ]);
        $result = (new FollowUpSuggestionService($llm))->suggest('The last attempt was shorter.', 'Repeat the cue.',
            ['domain' => 'behavior', 'problem_types' => ['transition']], [], [['role' => 'assistant', 'content' => $question]], 'en', [
                'observation_round' => 1,
                'asked_questions' => [['question' => $question, 'scope' => 'behavior:transition', 'observation_round' => 0]],
            ]);
        $this->assertSame($question, $result['question']);
    }
}

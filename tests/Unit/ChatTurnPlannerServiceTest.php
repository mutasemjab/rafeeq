<?php

namespace Tests\Unit;

use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Config;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChatTurnPlannerServiceTest extends TestCase
{
    public function test_it_selects_one_dynamic_clarification_question(): void
    {
        Config::set('ai.turn_planner_model', 'planner-model');
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')
            ->once()
            ->withArgs(function (array $messages, array $schema, array $options): bool {
                return ($schema['properties']['action']['enum'] ?? []) === [
                    'answer',
                    'ask_clarification',
                    'refer_to_specialist',
                ] && ($options['schema_name'] ?? null) === 'rafeeq_turn_plan';
            })
            ->andReturn([
                'action' => 'ask_clarification',
                'domain' => 'behavior',
                'case_specific' => true,
                'information_sufficient' => false,
                'reason' => 'Antecedent and consequence are missing.',
                'question' => 'ماذا يحدث مباشرة قبل الصراخ، وماذا تفعلون بعده؟',
                'missing_fields' => ['antecedent', 'consequence'],
                'search_queries' => [],
                'follow_up_needed' => true,
                'confidence' => 0.94,
            ]);

        $plan = (new ChatTurnPlannerService($llm))->plan(
            'ابني يصرخ كثيرًا',
            ['profile' => ['age' => 5], 'memories' => []],
            [],
            'behavior'
        );

        $this->assertSame('ask_clarification', $plan['action']);
        $this->assertSame(['antecedent', 'consequence'], $plan['missing_fields']);
        $this->assertNotEmpty($plan['question']);
    }

    public function test_it_rejects_an_answer_marked_information_insufficient(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'action' => 'answer',
            'domain' => 'behavior',
            'case_specific' => true,
            'information_sufficient' => false,
            'reason' => 'Missing information.',
            'question' => null,
            'missing_fields' => ['frequency'],
            'search_queries' => [],
            'follow_up_needed' => false,
            'confidence' => 0.5,
        ]);

        $this->expectException(RuntimeException::class);
        (new ChatTurnPlannerService($llm))->plan('Help', ['profile' => null, 'memories' => []]);
    }

    public function test_it_enforces_one_clarification_question_per_turn(): void
    {
        Config::set('ai.max_clarifying_questions_per_turn', 1);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'action' => 'ask_clarification',
            'domain' => 'speech_language',
            'case_specific' => true,
            'information_sufficient' => false,
            'reason' => 'Several details are missing.',
            'question' => 'كم عمر الطفل؟ متى بدأ الكلام؟ وهل فُحص السمع؟',
            'missing_fields' => ['age', 'onset', 'hearing'],
            'search_queries' => [],
            'follow_up_needed' => true,
            'confidence' => 0.9,
        ]);

        $plan = (new ChatTurnPlannerService($llm))->plan(
            'ابني متأخر بالكلام',
            ['profile' => null, 'memories' => []]
        );

        $this->assertSame('كم عمر الطفل؟', $plan['question']);
    }

    public function test_it_analyzes_a_requested_outcome_before_asking_another_question(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'action' => 'ask_clarification',
            'domain' => 'behavior',
            'case_specific' => true,
            'information_sufficient' => false,
            'reason' => 'More information may be useful.',
            'question' => 'هل تغيّر شيء آخر؟',
            'missing_fields' => ['other_changes'],
            'search_queries' => ['visual schedules child behavior'],
            'follow_up_needed' => true,
            'confidence' => 0.8,
        ]);

        $plan = (new ChatTurnPlannerService($llm))->plan(
            'جربنا الجدول ثلاثة أيام وانخفض الصراخ من خمس مرات إلى مرتين.',
            ['profile' => ['age_months' => 60], 'memories' => []],
            [],
            'behavior',
            [],
            ['follow_up_needed' => true]
        );

        $this->assertSame('answer', $plan['action']);
        $this->assertTrue($plan['information_sufficient']);
        $this->assertNull($plan['question']);
        $this->assertSame([], $plan['missing_fields']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\AI\AnswerQualityService;
use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\Providers\FakeLlmProvider;
use Tests\TestCase;

class FakeLlmProviderTest extends TestCase
{
    public function test_quality_schema_with_action_is_not_mistaken_for_a_turn_plan(): void
    {
        config(['ai.answer_quality_gate_enabled' => true]);
        $result = (new AnswerQualityService(new FakeLlmProvider()))->review(
            'Help with the transition.',
            'Use the documented cue [KB_SOURCE_1].',
            ['action' => 'answer'],
            [],
            '[KB_SOURCE_1] Approved transition guidance.',
            'en'
        );

        $this->assertSame('approve', $result['action']);
        $this->assertTrue($result['passed']);
        $this->assertSame(1.0, $result['scores']['grounding']);
    }

    public function test_turn_planner_still_receives_a_plan_from_the_fake(): void
    {
        config(['ai.turn_planner_enabled' => true]);
        $result = (new ChatTurnPlannerService(new FakeLlmProvider()))->plan(
            'How can I help with a transition?', []
        );

        $this->assertSame('answer', $result['action']);
        $this->assertTrue($result['information_sufficient']);
        $this->assertFalse($result['follow_up_needed']);
    }
}

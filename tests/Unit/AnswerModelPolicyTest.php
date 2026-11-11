<?php

namespace Tests\Unit;

use App\Services\AI\AnswerModelPolicy;
use Tests\TestCase;

class AnswerModelPolicyTest extends TestCase
{
    public function test_personalized_fast_answers_get_reasoning_without_changing_the_model_or_downgrading_config(): void
    {
        config(['ai.fast_answer_model' => 'fast-model', 'ai.fast_answer_reasoning_effort' => 'none',
            'ai.fast_answer_max_output_tokens' => 800]);
        $plan = ['risk_level' => 'low', 'response_mode' => 'fast', 'case_specific' => true];
        $policy = new AnswerModelPolicy();
        $options = $policy->options($plan, 'Help with following instructions.');
        $this->assertSame('fast-model', $options['model']);
        $this->assertSame('low', $options['reasoning_effort']);
        $this->assertGreaterThanOrEqual(1600, $options['max_completion_tokens']);
        config(['ai.fast_answer_reasoning_effort' => 'medium']);
        $this->assertSame('medium', $policy->options($plan, 'Help')['reasoning_effort']);
    }

    public function test_fast_and_deep_use_different_models_and_compute_budgets(): void
    {
        config(['ai.fast_answer_model' => 'fast-model', 'ai.fast_answer_reasoning_effort' => 'none',
            'ai.answer_model' => 'deep-model', 'ai.answer_reasoning_effort' => 'medium']);
        $policy = new AnswerModelPolicy();
        $fast = $policy->options(['risk_level' => 'low', 'response_mode' => 'fast'], 'Explain communication');
        $deep = $policy->options(['risk_level' => 'low', 'response_mode' => 'deep'], 'Explain communication');
        $this->assertSame('fast-model', $fast['model']);
        $this->assertSame('none', $fast['reasoning_effort']);
        $this->assertSame('deep-model', $deep['model']);
        $this->assertSame('medium', $deep['reasoning_effort']);
        $this->assertTrue($policy->requiresCarefulReview(['risk_level' => 'low'], 'هل أغير جرعة الدواء؟'));
    }
    public function test_primary_model_is_preserved_for_high_risk_complex_routes_and_medication(): void
    {
        config(['ai.routine_answer_model'=>'gpt-6-sol']);
        $policy=new AnswerModelPolicy();
        $this->assertSame([], $policy->options(['risk_level'=>'high'],'Help'));
        $this->assertSame([], $policy->options(['risk_level'=>'low','after_referral'=>true],'Explain available support.'));
        $this->assertSame([], $policy->options(['risk_level'=>'moderate','pathway_state'=>['active_pathways'=>['psychosis']]],'Help'));
        $this->assertSame([], $policy->options(['risk_level'=>'low'],'هل أغير جرعة الدواء؟'));
        $this->assertSame(['model'=>'gpt-6-sol'], $policy->options(['risk_level'=>'low','action'=>'answer'],'How can I use the app?'));
        config(['ai.routine_answer_model'=>null]);
        $this->assertSame([], $policy->options(['risk_level'=>'low'],'Help'));
    }
}

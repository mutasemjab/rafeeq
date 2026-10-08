<?php

namespace Tests\Unit;

use App\Services\AI\AnswerModelPolicy;
use Tests\TestCase;

class AnswerModelPolicyTest extends TestCase
{
    public function test_primary_model_is_preserved_for_high_risk_complex_routes_and_medication(): void
    {
        config(['ai.routine_answer_model'=>'gpt-6-sol']);
        $policy=new AnswerModelPolicy();
        $this->assertSame([], $policy->options(['risk_level'=>'high'],'Help'));
        $this->assertSame([], $policy->options(['risk_level'=>'moderate','pathway_state'=>['active_pathways'=>['psychosis']]],'Help'));
        $this->assertSame([], $policy->options(['risk_level'=>'low'],'هل أغير جرعة الدواء؟'));
        $this->assertSame(['model'=>'gpt-6-sol'], $policy->options(['risk_level'=>'low','action'=>'answer'],'How can I use the app?'));
        config(['ai.routine_answer_model'=>null]);
        $this->assertSame([], $policy->options(['risk_level'=>'low'],'Help'));
    }
}

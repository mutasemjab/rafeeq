<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\SafetyTriageService;
use Mockery;
use Tests\TestCase;

class SafetyTriageServiceTest extends TestCase
{
    public function test_direct_breathing_emergency_is_detected_without_model_call(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new SafetyTriageService($llm))->evaluate('ابني لا يستطيع التنفس الآن');

        $this->assertSame('emergency', $result['level']);
        $this->assertSame('breathing_emergency', $result['reason_code']);
        $this->assertSame('deterministic_rule', $result['source']);
    }

    public function test_routine_message_without_safety_cue_skips_model_call(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new SafetyTriageService($llm))->evaluate('كيف أساعد ابني في تعلم كلمات جديدة؟');

        $this->assertSame('routine', $result['level']);
        $this->assertSame('no_safety_cue', $result['reason_code']);
    }

    public function test_sudden_regression_is_escalated_without_model_call(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new SafetyTriageService($llm))->evaluate('ابني فقد مهارة الكلام بشكل مفاجئ');

        $this->assertSame('urgent_specialist', $result['level']);
        $this->assertSame(['developmental_regression'], $result['flags']);
        $this->assertSame('deterministic_rule', $result['source']);
    }
}

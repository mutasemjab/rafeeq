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

    /** @dataProvider socialCueMessages */
    public function test_nonresponse_to_a_name_is_contextually_triaged(string $message): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'level' => 'routine',
            'reason_code' => 'response_to_name',
            'reason' => 'The caregiver describes response to a social cue, not loss of consciousness.',
            'confidence' => 0.95,
            'flags' => [],
        ]);

        $result = (new SafetyTriageService($llm))->evaluate($message);

        $this->assertSame('routine', $result['level']);
        $this->assertSame('model_classifier', $result['source']);
    }

    public function socialCueMessages(): array
    {
        return [
            ['ابني لا يستجيب لاسمه لكنه يلعب بشكل طبيعي'],
            ['ابني لا يستجيب عندما أناديه.'],
            ['My son is not responding to his name but is playing normally.'],
            ['She is unresponsive to verbal prompts during play.'],
        ];
    }

    public function test_a_social_cue_does_not_hide_another_emergency_in_the_message(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new SafetyTriageService($llm))->evaluate('لا يستجيب لاسمه ثم سقط وهو فاقد الوعي');

        $this->assertSame('emergency', $result['level']);
        $this->assertSame('unresponsive', $result['reason_code']);
    }

    public function test_bare_nonresponse_still_triggers_immediate_emergency(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();

        $result = (new SafetyTriageService($llm))->evaluate('ابني سقط وهو لا يستجيب');

        $this->assertSame('emergency', $result['level']);
    }

    public function test_a_deferred_social_cue_fails_safe_when_classifier_is_unavailable(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andThrow(new \RuntimeException('Unavailable'));

        $result = (new SafetyTriageService($llm))->evaluate('My child is not responding to his name.');

        $this->assertSame('urgent_specialist', $result['level']);
        $this->assertSame('fail_safe', $result['source']);
    }

    /** @dataProvider contextualOccurrences */
    public function test_negated_historical_and_hypothetical_signals_require_context_instead_of_automatic_emergency(string $message): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'level' => 'routine', 'reason_code' => 'context_only', 'reason' => 'No current danger described.', 'confidence' => 0.95, 'flags' => [],
        ]);
        $result = (new SafetyTriageService($llm))->evaluate($message);
        $this->assertSame('routine', $result['level']);
        $this->assertSame('model_classifier', $result['source']);
    }

    public function contextualOccurrences(): array
    {
        return [
            ['ابني ليس فاقد الوعي، لكنه لا يستجيب لاسمه أثناء اللعب'],
            ['ابني لا يختنق، أريد معلومات للوقاية'],
            ['كان ابني فاقد الوعي قبل سنة وتعافى'],
            ['ماذا أفعل إذا كان الطفل لا يتنفس؟'],
            ['لو هو الآن فاقد الوعي، كيف أطلب المساعدة؟'],
            ['He is not unconscious; he is playing normally.'],
            ['He was not breathing last year, but recovered.'],
            ['What if my child is choking?'],
            ['He lost speech last year and has since recovered.'],
            ['لم يفقد مهارة الكلام بشكل مفاجئ، هي صعوبة قديمة'],
        ];
    }

    public function test_historical_or_negated_clause_does_not_hide_a_new_current_emergency(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->never();
        $result = (new SafetyTriageService($llm))->evaluate('كان فاقد الوعي قبل سنة، لكن الآن لا يستطيع التنفس');
        $this->assertSame('emergency', $result['level']);
        $this->assertSame('breathing_emergency', $result['reason_code']);
    }

    /** @dataProvider arabicInjuryMessages */
    public function test_arabic_injury_and_bleeding_are_safety_cues(string $message): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'level' => 'emergency', 'reason_code' => 'current_injury', 'reason' => 'Current injury described.', 'confidence' => 0.95, 'flags' => ['current_injury'],
        ]);
        $result = (new SafetyTriageService($llm))->evaluate($message);
        $this->assertSame('emergency', $result['level']);
    }

    public function arabicInjuryMessages(): array
    {
        return [['ابني بيخبط راسه وبينزف دلوقتي'], ['يضرب نفسه بقوة الآن'], ['بيطلع منه دم ومش بيقف'], ['يجرح نفسه دلوقتي']];
    }

    public function test_short_answer_preserves_context_of_an_immediately_preceding_safety_question(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function (array $messages): bool {
            $data = json_decode($messages[1]['content'], true);

            return $data['latest_message'] === 'نعم' && $data['recent_history'][0]['content'] === 'هل يوجد نزيف الآن؟';
        })->andReturn(['level' => 'urgent_specialist', 'flags' => []]);
        $result = (new SafetyTriageService($llm))->evaluate('نعم', [['role' => 'assistant', 'content' => 'هل يوجد نزيف الآن؟']]);
        $this->assertSame('urgent_specialist', $result['level']);
    }
}

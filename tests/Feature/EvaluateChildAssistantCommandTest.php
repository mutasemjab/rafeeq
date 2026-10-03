<?php

namespace Tests\Feature;

use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\DomainGuardService;
use App\Services\AI\SafetyTriageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EvaluateChildAssistantCommandTest extends TestCase
{
    public function test_offline_evaluation_runs_deterministic_emergency_case(): void
    {
        Config::set('ai.provider', 'fake');

        $this->artisan('ai:evaluate-child-assistant', [
            '--case' => 'emergency_breathing_ar',
            '--json' => true,
        ])->assertExitCode(0);
    }

    public function test_offline_suite_explicitly_skips_all_model_dependent_contextual_safety_cases(): void
    {
        Config::set('ai.provider', 'fake');
        $code = Artisan::call('ai:evaluate-child-assistant', ['--json' => true]);
        $result = json_decode(Artisan::output(), true);
        $this->assertSame(0, $code);
        $this->assertSame(19, $result['summary']['total']);
        $this->assertSame(3, $result['summary']['passed']);
        $this->assertSame(16, $result['summary']['skipped']);
    }

    public function test_safety_only_case_does_not_call_domain_or_planner(): void
    {
        $this->mock(SafetyTriageService::class)->shouldReceive('evaluate')->once()->andReturn(['level' => 'routine']);
        $this->mock(DomainGuardService::class)->shouldNotReceive('evaluate');
        $this->mock(ChatTurnPlannerService::class)->shouldNotReceive('plan');
        $code = Artisan::call('ai:evaluate-child-assistant', [
            '--case' => 'safety_resolved_breathing_history_ar', '--live' => true, '--json' => true,
        ]);
        $this->assertSame(0, $code);
        $this->assertSame('passed', data_get(json_decode(Artisan::output(), true), 'results.0.status'));
    }

    /** @dataProvider clarificationLanguageCases */
    public function test_live_evaluation_checks_clarification_language(string $question, string $language, int $exitCode): void
    {
        $this->mock(SafetyTriageService::class)->shouldReceive('evaluate')->once()->andReturn(['level' => 'routine']);
        $this->mock(DomainGuardService::class)->shouldReceive('evaluate')->once()->andReturn([
            'allowed' => true,
            'category' => 'speech_language',
        ]);
        $this->mock(ChatTurnPlannerService::class)->shouldReceive('plan')->once()->andReturn([
            'action' => 'ask_clarification',
            'evidence_required' => true,
            'question' => $question,
        ]);

        $actualExitCode = Artisan::call('ai:evaluate-child-assistant', [
            '--case' => 'speech_missing_age_en',
            '--live' => true,
            '--json' => true,
        ]);
        $result = json_decode(Artisan::output(), true);

        $this->assertSame($exitCode, $actualExitCode);
        $this->assertSame($language, data_get($result, 'results.0.question_language'));
        if ($exitCode !== 0) {
            $this->assertSame('Clarification language mismatch; expected en.', data_get($result, 'results.0.details'));
        }
    }

    public static function clarificationLanguageCases(): array
    {
        return [
            ['How old is your child?', 'en', 0],
            ['كم عمر طفلك؟', 'ar', 1],
        ];
    }
}

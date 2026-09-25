<?php

namespace Tests\Feature;

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
}

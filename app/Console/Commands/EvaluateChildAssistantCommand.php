<?php

namespace App\Console\Commands;

use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\DomainGuardService;
use App\Services\AI\SafetyTriageService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class EvaluateChildAssistantCommand extends Command
{
    protected $signature = 'ai:evaluate-child-assistant
        {--case= : Run one case id}
        {--live : Run model-based domain and turn-planning checks}
        {--json : Return machine-readable JSON}';

    protected $description = 'Run the child-assistant safety, domain, and next-action evaluation set';

    public function handle(
        SafetyTriageService $safety,
        DomainGuardService $domainGuard,
        ChatTurnPlannerService $planner
    ): int {
        $cases = $this->cases();
        if ($this->option('case')) {
            $cases = array_values(array_filter(
                $cases,
                fn (array $case): bool => ($case['id'] ?? null) === $this->option('case')
            ));
        }
        if ($cases === []) {
            throw new RuntimeException('No evaluation cases matched the requested selection.');
        }

        $results = [];
        foreach ($cases as $case) {
            $results[] = $this->evaluateCase($case, $safety, $domainGuard, $planner);
        }

        $failed = collect($results)->where('status', 'failed')->count();
        $skipped = collect($results)->where('status', 'skipped')->count();
        $payload = [
            'summary' => [
                'total' => count($results),
                'passed' => count($results) - $failed - $skipped,
                'failed' => $failed,
                'skipped' => $skipped,
                'live' => (bool) $this->option('live'),
            ],
            'results' => $results,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Case', 'Status', 'Safety', 'Action', 'Domain', 'Details'],
                array_map(fn (array $result): array => [
                    $result['id'],
                    $result['status'],
                    $result['actual_safety'] ?? '-',
                    $result['actual_action'] ?? '-',
                    $result['actual_domain'] ?? '-',
                    $result['details'] ?? '',
                ], $results)
            );
            $this->info(sprintf(
                'Passed: %d | Failed: %d | Skipped: %d',
                $payload['summary']['passed'],
                $failed,
                $skipped
            ));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function evaluateCase(
        array $case,
        SafetyTriageService $safety,
        DomainGuardService $domainGuard,
        ChatTurnPlannerService $planner
    ): array {
        $result = ['id' => $case['id'], 'status' => 'passed'];

        try {
            if (($case['requires_model'] ?? false) && ! $this->option('live')) {
                return array_merge($result, [
                    'status' => 'skipped',
                    'details' => 'Use --live for model-based evaluation.',
                ]);
            }

            $history = $case['history'] ?? [];
            $safetyResult = $safety->evaluate((string) $case['message'], $history);
            $result['actual_safety'] = $safetyResult['level'] ?? null;
            if ($result['actual_safety'] !== ($case['expected_safety'] ?? null)) {
                return array_merge($result, [
                    'status' => 'failed',
                    'details' => 'Safety mismatch; expected '.($case['expected_safety'] ?? 'null').'.',
                ]);
            }

            if ($result['actual_safety'] !== 'routine' || ! $this->option('live')) {
                return $result;
            }

            $domain = $domainGuard->evaluate((string) $case['message'], $history);
            $result['actual_domain'] = $domain['category'] ?? null;
            if (($case['expected_allowed'] ?? true) !== (bool) ($domain['allowed'] ?? false)) {
                return array_merge($result, ['status' => 'failed', 'details' => 'Domain allowance mismatch.']);
            }
            if (! ($domain['allowed'] ?? false)) {
                return $result;
            }

            $plan = $planner->plan(
                (string) $case['message'],
                $case['child_context'] ?? ['profile' => null, 'memories' => []],
                $history,
                $domain['category'] ?? null,
                $domain['search_queries'] ?? [],
                $case['conversation_state'] ?? []
            );
            $result['actual_action'] = $plan['action'] ?? null;
            if ($result['actual_action'] !== ($case['expected_action'] ?? null)) {
                return array_merge($result, [
                    'status' => 'failed',
                    'details' => 'Action mismatch; expected '.($case['expected_action'] ?? 'null').'.',
                ]);
            }

            return $result;
        } catch (Throwable $exception) {
            return array_merge($result, [
                'status' => 'failed',
                'details' => $exception::class.': '.$exception->getMessage(),
            ]);
        }
    }

    private function cases(): array
    {
        $path = resource_path('ai/evals/child_assistant_cases.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('The child-assistant evaluation dataset is missing or invalid.');
        }

        return $decoded;
    }
}

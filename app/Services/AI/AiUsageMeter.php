<?php

namespace App\Services\AI;

use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiUsageMeter
{
    private ?array $context = null;

    public function begin(?Message $message = null, string $purpose = 'chat', int $attempt = 1): ?array
    {
        $previous = $this->context;
        $this->context = ['run_id' => (string) Str::uuid(), 'purpose' => $purpose, 'attempt' => $attempt, 'observed_requests' => 0];
        if ($message) $this->attach($message);
        return $previous;
    }

    public function attach(Message $message): void
    {
        if ($this->context === null) return;
        $this->context = array_merge($this->context, [
            'user_id' => $message->user_id, 'conversation_id' => $message->conversation_id,
            'user_message_id' => $message->id,
            'question_key' => hash_hmac('sha256', (string) $message->id, (string) config('app.key')),
        ]);
        if (config('ai_costs.evaluation') || (str_starts_with((string) $message->user?->email, 'rafiq-qa-')
            && str_ends_with((string) $message->user?->email, '@example.com'))) $this->context['purpose'] = 'evaluation';
    }

    public function restore(?array $previous): void
    {
        $this->context = $previous;
    }

    public function finishQuestion(bool $succeeded): void
    {
        // A deterministic greeting/clarification can cost exactly zero. Keep it visible
        // without inventing an API call or hiding requests whose usage was unavailable.
        if (isset($this->context['user_message_id']) && $this->context['observed_requests'] === 0) {
            $this->record('local_reply', 'none', 'local_reply', 0, $succeeded);
        }
    }

    public function record(string $operation, string $model, ?string $stage, int $milliseconds, bool $succeeded, array $response = [], ?string $requestId = null): void
    {
        if (!config('ai_costs.enabled', true)) return;
        if ($this->context !== null) $this->context['observed_requests']++;
        try {
            $cost = app(AiCostCalculator::class)->calculate($response, $model, $operation);
            $row = array_merge($this->context ?? ['run_id' => (string) Str::uuid(), 'purpose' => 'background', 'attempt' => 1], $cost, [
                'variant' => config('ai_costs.optimize_prompt_cache', true) ? 'stable_cache_v1' : 'baseline',
                'operation' => $operation, 'stage' => $stage ?? $operation,
                'provider_request_id' => $requestId ? mb_substr($requestId, 0, 150) : null,
                'succeeded' => $succeeded, 'duration_ms' => $milliseconds, 'created_at' => now(),
            ]);
            $row['usage_details'] = json_encode($row['usage_details']);
            unset($row['observed_requests']);
            try {
                DB::table('ai_request_usages')->insert($row);
            } catch (\Illuminate\Database\QueryException $error) {
                // The owner can delete a conversation/account while the provider is running.
                // Preserve the anonymous cost even if a foreign-key target just disappeared.
                if (!in_array((string) $error->getCode(), ['23000', '23503'], true)) throw $error;
                $row['user_id'] = $row['conversation_id'] = $row['user_message_id'] = null;
                DB::table('ai_request_usages')->insert($row);
            }
        } catch (Throwable $error) {
            // Metering failure must not take the assistant down. Never claim a zero cost.
            Log::warning('ai.cost.record_failed', ['operation' => $operation, 'stage' => $stage, 'exception' => $error::class,
                'cost' => $cost ?? null, 'run_id' => $this->context['run_id'] ?? null]);
        }
    }
}

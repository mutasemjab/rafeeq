<?php

namespace App\Services\AI;

class AiCostCalculator
{
    /** Usage is provider-owned. Never infer tokens from prompt length or duration. */
    public function calculate(array $data, string $requestedModel, string $operation): array
    {
        if ($operation === 'local_reply') {
            return ['model' => 'none', 'service_tier' => null, 'input_tokens' => 0, 'output_tokens' => 0,
                'cached_input_tokens' => 0, 'cache_write_tokens' => 0, 'web_search_calls' => 0,
                'estimated_cost_usd' => 0, 'pricing_status' => 'local_no_api',
                'pricing_version' => config('ai_costs.pricing_version'), 'usage_details' => []];
        }
        $usage = (array) ($data['usage'] ?? []);
        $model = (string) ($data['model'] ?? $requestedModel);
        $baseModel = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model);
        // Model names contain dots, so use a literal key lookup.
        $rates = config('ai_costs.models', [])[$baseModel] ?? null;
        $input = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? ($operation === 'embeddings' ? 0 : null);
        $details = (array) ($usage['input_tokens_details'] ?? $usage['prompt_tokens_details'] ?? []);
        $cached = (int) ($details['cached_tokens'] ?? 0);
        $written = (int) ($details['cache_write_tokens'] ?? 0);
        $writeDetails = (array) ($details['cache_write_tokens_details'] ?? []);
        $write12h = (int) ($writeDetails['ttl_12h_tokens'] ?? 0);
        // This app requests only 30m writes. Older Chat Completions usage omits the split.
        $write30m = (int) ($writeDetails['ttl_30m_tokens'] ?? ($written - $write12h));
        $searches = 0;
        $unknownTool = false;
        foreach ((array) ($data['output'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'web_search_call') continue;
            $action = data_get($item, 'action.type');
            if ($action === 'search') $searches++;
            elseif (! in_array($action, ['open_page', 'find_in_page'], true)) $unknownTool = true;
        }
        $tier = $data['service_tier'] ?? 'default';
        $multiplier = ['default' => 1, 'standard' => 1, 'priority' => 2, 'fast' => 2, 'flex' => 0.5][$tier] ?? null;
        $status = !is_numeric($input) || !is_numeric($output) ? 'usage_missing'
            : (!$rates ? 'model_unpriced' : ($multiplier === null ? 'tier_unpriced' : ($unknownTool ? 'tool_usage_incomplete' : 'estimated')));
        if ($input !== null && ($cached < 0 || $written < 0 || $cached + $written > $input
            || $write30m < 0 || $write12h < 0 || $write30m + $write12h !== $written)) $status = 'usage_inconsistent';
        $cost = null;
        if ($status === 'estimated') {
            $long = $input > 272000 && isset($rates['long_input']);
            $inRate = $rates[$long ? 'long_input' : 'input'] * $multiplier;
            $outRate = $rates[$long ? 'long_output' : 'output'] * $multiplier;
            $cacheRate = $rates[$long ? 'long_cached' : 'cached'] * $multiplier;
            $cost = (($input - $cached - $written) * $inRate + $cached * $cacheRate
                + $write30m * $inRate * 1.25 + $write12h * $inRate * 2.5 + $output * $outRate) / 1000000
                + $searches * (float) config('ai_costs.web_search_usd', 0.01);
        }

        return [
            'model' => $model, 'service_tier' => $tier,
            'input_tokens' => $input, 'output_tokens' => $output,
            'cached_input_tokens' => $cached, 'cache_write_tokens' => $written,
            'web_search_calls' => $searches,
            'estimated_cost_usd' => $cost === null ? null : round($cost, 8),
            'pricing_status' => $status,
            'pricing_version' => config('ai_costs.pricing_version'),
            // Only numeric counters; never persist prompts, evidence, URLs, or response text.
            'usage_details' => ['write_30m_tokens' => $write30m, 'write_12h_tokens' => $write12h,
                'reasoning_tokens' => (int) data_get($usage, 'output_tokens_details.reasoning_tokens', data_get($usage, 'completion_tokens_details.reasoning_tokens', 0)),
                'service_tier_reported' => isset($data['service_tier'])],
        ];
    }
}

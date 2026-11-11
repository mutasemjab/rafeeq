<?php

namespace App\Services\AI;

class PromptCachePolicy
{
    /** Preserve every instruction and source byte; cache only the stable instruction prefix. */
    public function apply(array $payload, string $endpoint): array
    {
        if (config('ai_costs.evaluation') && ($partition = config('ai_costs.cache_partition'))) {
            $payload['prompt_cache_key'] = (string) $partition;
        }
        if (!config('ai_costs.optimize_prompt_cache', true)
            || !preg_match('/^gpt-(?:5\.[6-9]|[6-9])(?:[.\-]|$)/', (string) ($payload['model'] ?? ''))) return $payload;
        $payload['prompt_cache_options'] = ['mode' => 'explicit', 'ttl' => '30m'];
        if ($endpoint === 'responses') {
            $instructions = $payload['instructions'] ?? '';
            if (is_string($instructions) && $instructions !== '') {
                array_unshift($payload['input'], ['role' => 'system', 'content' => [[
                    'type' => 'input_text', 'text' => $instructions,
                    'prompt_cache_breakpoint' => ['mode' => 'explicit'],
                ]]]);
                unset($payload['instructions']);
            }
        } else {
            // Do not cache a changing user suffix. Mark only the last leading instruction.
            $last = null;
            foreach ($payload['messages'] as $i => $message) {
                if (!in_array($message['role'] ?? '', ['system', 'developer'], true) || !is_string($message['content'] ?? null)) break;
                $last = $i;
            }
            if ($last !== null) $payload['messages'][$last]['content'] = [[
                'type' => 'text', 'text' => $payload['messages'][$last]['content'],
                'prompt_cache_breakpoint' => ['mode' => 'explicit'],
            ]];
        }
        return $payload;
    }
}

<?php

namespace Tests\Unit;

use App\Services\AI\AiCostCalculator;
use Tests\TestCase;

class AiCostCalculatorTest extends TestCase
{
    public function test_mixed_cache_reads_writes_and_reasoning_are_charged_once(): void
    {
        $result = app(AiCostCalculator::class)->calculate(['usage' => [
            'input_tokens' => 12000, 'output_tokens' => 1000,
            'input_tokens_details' => ['cached_tokens' => 4000, 'cache_write_tokens' => 7000,
                'cache_write_tokens_details' => ['ttl_30m_tokens' => 3000, 'ttl_12h_tokens' => 4000]],
            'output_tokens_details' => ['reasoning_tokens' => 700],
        ]], 'gpt-6-astra', 'responses');
        $this->assertSame('estimated', $result['pricing_status']);
        $this->assertSame(0.2015, $result['estimated_cost_usd']);
        $this->assertSame(700, $result['usage_details']['reasoning_tokens']);
    }

    public function test_raw_chat_usage_and_snapshot_names_keep_cache_discounts(): void
    {
        $result = app(AiCostCalculator::class)->calculate(['model' => 'gpt-5.6-luna-2026-08-01', 'usage' => [
            'prompt_tokens' => 10000, 'completion_tokens' => 1000,
            'prompt_tokens_details' => ['cached_tokens' => 2000, 'cache_write_tokens' => 4000],
        ]], 'gpt-5.6-luna', 'chat_json');
        $this->assertSame(0.00304, $result['estimated_cost_usd']);
    }

    public function test_web_search_counts_search_actions_not_page_reads(): void
    {
        $result = app(AiCostCalculator::class)->calculate(['usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            'output' => array_map(fn ($type) => ['type' => 'web_search_call', 'action' => ['type' => $type]], ['search', 'open_page', 'find_in_page', 'search']),
        ], 'gpt-6-astra', 'responses');
        $this->assertSame(2, $result['web_search_calls']);
        $this->assertSame(0.02, $result['estimated_cost_usd']);
    }

    /** @dataProvider unknownCosts */
    public function test_incomplete_usage_is_never_reported_as_free(array $data, string $model, string $status): void
    {
        $result = app(AiCostCalculator::class)->calculate($data, $model, 'responses');
        $this->assertSame($status, $result['pricing_status']);
        $this->assertNull($result['estimated_cost_usd']);
    }

    public static function unknownCosts(): array
    {
        $usage = ['input_tokens' => 100, 'output_tokens' => 10];
        return [
            [[], 'gpt-6-astra', 'usage_missing'],
            [['usage' => $usage], 'new-unpriced-model', 'model_unpriced'],
            [['usage' => $usage, 'service_tier' => 'special'], 'gpt-6-astra', 'tier_unpriced'],
            [['usage' => $usage, 'output' => [['type' => 'web_search_call']]], 'gpt-6-astra', 'tool_usage_incomplete'],
            [['usage' => $usage + ['input_tokens_details' => ['cached_tokens' => 200]]], 'gpt-6-astra', 'usage_inconsistent'],
        ];
    }

    public function test_long_context_priority_and_embeddings_are_priced(): void
    {
        $calculator = app(AiCostCalculator::class);
        $result = $calculator->calculate(['usage' => ['input_tokens' => 300000, 'output_tokens' => 1000], 'service_tier' => 'priority'], 'gpt-6-astra', 'responses');
        $this->assertSame(12.15, $result['estimated_cost_usd']);
        $this->assertSame(0.00013, $calculator->calculate(['usage' => ['prompt_tokens' => 1000]], 'text-embedding-3-large', 'embeddings')['estimated_cost_usd']);
    }
}

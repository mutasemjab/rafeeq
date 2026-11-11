<?php

return [
    // Estimates, not invoices. Snapshot standard USD prices per million tokens.
    'pricing_version' => 'openai-2026-11-11',
    'pricing_source' => 'https://developers.openai.com/api/docs/pricing',
    'enabled' => (bool) env('AI_COST_TRACKING_ENABLED', true),
    'optimize_prompt_cache' => (bool) env('AI_OPTIMIZE_PROMPT_CACHE', true),
    'evaluation' => false,
    'web_search_usd' => 0.01,
    'models' => [
        'gpt-6-astra' => ['input' => 10, 'output' => 50, 'cached' => 1, 'long_input' => 20, 'long_output' => 75, 'long_cached' => 2],
        'gpt-6-sol' => ['input' => 2, 'output' => 10, 'cached' => 0.2, 'long_input' => 4, 'long_output' => 15, 'long_cached' => 0.4],
        'gpt-5.6-luna' => ['input' => 0.2, 'output' => 1.2, 'cached' => 0.02, 'long_input' => 0.4, 'long_output' => 1.8, 'long_cached' => 0.04],
        'text-embedding-3-large' => ['input' => 0.13, 'output' => 0, 'cached' => 0.13],
        'text-embedding-3-small' => ['input' => 0.02, 'output' => 0, 'cached' => 0.02],
    ],
];

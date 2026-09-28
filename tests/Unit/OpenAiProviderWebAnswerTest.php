<?php

namespace Tests\Unit;

use App\Services\AI\OpenAiConfigResolver;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiProviderWebAnswerTest extends TestCase
{
    public function test_it_uses_responses_web_search_and_returns_clickable_sources(): void
    {
        Config::set('ai.openai_api_key', 'test-key');
        Config::set('openai.api_key', 'test-key');
        Config::set('ai.openai_web_search_enabled', true);
        Config::set('ai.answer_model', 'test-model');
        Config::set('ai.openai_web_search_allowed_domains', ['cdc.gov']);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'action' => [
                            'sources' => [[
                                'title' => 'CDC Child Development',
                                'url' => 'https://www.cdc.gov/child-development/',
                            ]],
                        ],
                    ],
                    [
                        'type' => 'message',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => 'A sourced answer.',
                            'annotations' => [],
                        ]],
                    ],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $result = (new OpenAiProvider(new OpenAiConfigResolver()))->answer([
            ['role' => 'system', 'content' => 'Use evidence.'],
            ['role' => 'user', 'content' => 'What should I know?'],
        ], ['web_search' => true, 'web_search_required' => true]);

        $this->assertSame('A sourced answer.', $result['content']);
        $this->assertTrue($result['used_web_search']);
        $this->assertSame('https://www.cdc.gov/child-development/', $result['sources'][0]['url']);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['tools'][0]['type'] === 'web_search'
                && $request['tools'][0]['filters']['allowed_domains'] === ['cdc.gov']
                && $request['tool_choice'] === 'required';
        });
    }

    public function test_it_uses_responses_api_even_when_web_search_is_not_needed(): void
    {
        Config::set('ai.openai_api_key', 'test-key');
        Config::set('openai.api_key', 'test-key');
        Config::set('ai.answer_model', 'test-model');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'An answer grounded in the supplied context.',
                        'annotations' => [],
                    ]],
                ]],
                'usage' => ['input_tokens' => 8, 'output_tokens' => 7, 'total_tokens' => 15],
            ], 200),
        ]);

        $result = (new OpenAiProvider(new OpenAiConfigResolver()))->answer([
            ['role' => 'system', 'content' => 'Use the supplied evidence.'],
            ['role' => 'user', 'content' => 'Summarize the next step.'],
        ]);

        $this->assertSame('An answer grounded in the supplied context.', $result['content']);
        $this->assertFalse($result['used_web_search']);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['model'] === 'test-model'
                && ! isset($request['tools'])
                && ! isset($request['tool_choice']);
        });
    }

    public function test_it_returns_only_sources_cited_by_the_answer_when_annotations_are_available(): void
    {
        Config::set('ai.openai_api_key', 'test-key');
        Config::set('openai.api_key', 'test-key');
        Config::set('ai.answer_model', 'test-model');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'action' => ['sources' => [
                            ['title' => 'Unused', 'url' => 'https://example.org/unused'],
                            ['title' => 'Used', 'url' => 'https://www.cdc.gov/used'],
                        ]],
                    ],
                    [
                        'type' => 'message',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => 'Cited answer.',
                            'annotations' => [[
                                'type' => 'url_citation',
                                'title' => 'Used',
                                'url' => 'https://www.cdc.gov/used',
                            ]],
                        ]],
                    ],
                ],
            ], 200),
        ]);

        $result = (new OpenAiProvider(new OpenAiConfigResolver()))->answer([
            ['role' => 'user', 'content' => 'Question'],
        ], ['web_search' => true, 'web_search_required' => true]);

        $this->assertCount(1, $result['sources']);
        $this->assertSame('https://www.cdc.gov/used', $result['sources'][0]['url']);
    }

    public function test_required_web_search_never_falls_back_to_an_unsearched_answer(): void
    {
        Config::set('ai.openai_api_key', 'test-key');
        Config::set('openai.api_key', 'test-key');
        Config::set('ai.openai_responses_fail_open', true);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Unavailable']], 503),
        ]);

        $this->expectException(RuntimeException::class);
        (new OpenAiProvider(new OpenAiConfigResolver()))->answer([
            ['role' => 'user', 'content' => 'Current guidance?'],
        ], ['web_search' => true, 'web_search_required' => true]);
    }
}

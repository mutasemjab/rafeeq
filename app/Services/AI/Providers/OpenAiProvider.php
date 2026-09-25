<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\OpenAiConfigResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;

    private ?string $organization;

    public function __construct(private OpenAiConfigResolver $configResolver)
    {
        $apiKey = $this->configResolver->apiKey();

        if (empty($apiKey)) {
            throw new RuntimeException(
                'OpenAI API key is not configured. Set OPENAI_API_KEY in your .env file or server environment.'
            );
        }

        $this->apiKey = $apiKey;
        $this->configResolver->syncIntoRuntimeConfig();
        $this->organization = config('openai.organization');
    }

    /**
     * Send a chat completion request and return the assistant's text content.
     *
     * @param  array  $messages  Array of ['role' => ..., 'content' => ...] messages.
     * @param  array  $options   Additional options merged into the request payload.
     *
     * @throws RuntimeException
     */
    public function chat(array $messages, array $options = []): string
    {
        try {
            $defaults = [
                'model' => config('ai.chat_model'),
                'messages' => $messages,
            ];

            $reasoningEffort = trim((string) config('ai.chat_reasoning_effort', ''));
            if ($reasoningEffort !== '') {
                $defaults['reasoning_effort'] = $reasoningEffort;
            }

            $maxCompletionTokens = (int) config('ai.chat_max_completion_tokens', 0);
            if ($maxCompletionTokens > 0) {
                $defaults['max_completion_tokens'] = $maxCompletionTokens;
            }

            $payload = array_merge($defaults, $options);

            $response = OpenAI::chat()->create($payload);

            $content = trim((string) ($response->choices[0]->message->content ?? ''));
            if ($content === '') {
                throw new RuntimeException('OpenAI chat returned an empty response.');
            }

            return $content;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI chat request failed: '.$e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function answer(array $messages, array $options = []): array
    {
        $webSearch = (bool) ($options['web_search'] ?? false);
        $webSearchRequired = (bool) ($options['web_search_required'] ?? false);
        unset($options['web_search'], $options['web_search_required']);

        $webSearch = $webSearch && (bool) config('ai.openai_web_search_enabled', true);

        try {
            return $this->responsesAnswer($messages, $webSearch, $webSearchRequired, $options);
        } catch (Throwable $exception) {
            Log::warning('ai.openai_responses.fallback', [
                'web_search_requested' => $webSearch,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            if (! config('ai.openai_responses_fail_open', true)) {
                throw $exception;
            }
        }

        $model = (string) ($options['model'] ?? config('ai.chat_model'));

        return [
            'content' => $this->chat($messages, $options),
            'sources' => [],
            'model' => $model,
            'used_web_search' => false,
            'usage' => [],
        ];
    }

    /**
     * Send a chat completion request that returns a schema-validated JSON object.
     *
     * A complete JSON Schema enables OpenAI Structured Outputs. The legacy
     * shorthand used by older callers is converted into a strict object schema.
     *
     * @param  array  $messages  Array of ['role' => ..., 'content' => ...] messages.
     * @param  array  $schema    Full JSON Schema or legacy key => primitive-type map.
     * @param  array  $options   Additional options merged into the request payload.
     *
     * @throws RuntimeException
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        try {
            $schemaName = $this->schemaName((string) ($options['schema_name'] ?? 'structured_response'));
            $strict = ($options['strict'] ?? true) !== false;
            unset($options['schema_name'], $options['strict']);

            $normalizedSchema = $schema !== [] ? $this->normalizeSchema($schema) : [];
            $responseFormat = $normalizedSchema === []
                ? ['type' => 'json_object']
                : [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $schemaName,
                        'strict' => $strict,
                        'schema' => $normalizedSchema,
                    ],
                ];

            $payload = array_merge([
                'model' => config('ai.chat_model'),
                'messages' => $messages,
                'response_format' => $responseFormat,
            ], $options);

            $response = OpenAI::chat()->create($payload);

            $message = $response->choices[0]->message ?? null;
            $refusal = is_object($message) && isset($message->refusal)
                ? trim((string) $message->refusal)
                : '';
            if ($refusal !== '') {
                throw new RuntimeException('OpenAI refused the structured request: '.$refusal);
            }

            $content = $message->content ?? '';
            if (! is_string($content) || trim($content) === '') {
                throw new RuntimeException('OpenAI chatJson returned an empty response.');
            }

            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                throw new RuntimeException(
                    'OpenAI chatJson returned non-array JSON: '.$content
                );
            }

            if ($normalizedSchema !== []) {
                $this->assertMatchesSchema($decoded, $normalizedSchema, '$');
            }

            return $decoded;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI chatJson request failed: '.$e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function normalizeSchema(array $schema): array
    {
        if (($schema['type'] ?? null) === 'object' && isset($schema['properties'])) {
            return $schema;
        }

        $properties = [];
        foreach ($schema as $key => $type) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $properties[$key] = $this->schemaProperty($type);
        }

        if ($properties === []) {
            return [];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private function responsesAnswer(
        array $messages,
        bool $webSearch,
        bool $webSearchRequired,
        array $options
    ): array
    {
        $model = (string) ($options['model'] ?? config('ai.answer_model', config('ai.chat_model')));
        $instructions = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->map(fn ($content): string => (string) $content)
            ->implode("\n\n");
        $input = collect($messages)
            ->reject(fn (array $message): bool => ($message['role'] ?? null) === 'system')
            ->map(fn (array $message): array => [
                'role' => in_array($message['role'] ?? null, ['user', 'assistant'], true)
                    ? $message['role']
                    : 'user',
                'content' => (string) ($message['content'] ?? ''),
            ])
            ->values()
            ->all();

        $payload = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => $input,
            'store' => false,
            'max_output_tokens' => (int) config('ai.chat_max_completion_tokens', 900),
        ];

        if ($webSearch) {
            $tool = [
                'type' => 'web_search',
                'search_context_size' => (string) config('ai.openai_web_search_context_size', 'medium'),
                'external_web_access' => true,
            ];
            $allowedDomains = $this->allowedWebDomains();
            if ($allowedDomains !== []) {
                $tool['filters'] = ['allowed_domains' => $allowedDomains];
            }

            $payload['tools'] = [$tool];
            $payload['tool_choice'] = $webSearchRequired ? 'required' : 'auto';
            $payload['include'] = ['web_search_call.action.sources'];
        }

        $reasoningEffort = trim((string) config('ai.answer_reasoning_effort', 'low'));
        if ($reasoningEffort !== '') {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        $request = Http::withToken($this->apiKey)
            ->acceptJson()
            ->connectTimeout(max(1, (int) config('ai.web_search_connect_timeout', 15)))
            ->timeout(max(10, (int) config('ai.web_search_request_timeout', 120)))
            ->retry(1, 750);

        if (is_string($this->organization) && trim($this->organization) !== '') {
            $request = $request->withHeaders([
                'OpenAI-Organization' => trim($this->organization),
            ]);
        }

        $response = $request->post('https://api.openai.com/v1/responses', $payload);
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'OpenAI Responses request failed with HTTP %d: %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 1000)
            ));
        }

        $data = $response->json();
        $content = '';
        $sources = [];
        $usedWebSearch = false;

        foreach ((array) ($data['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                $usedWebSearch = true;
                foreach ((array) data_get($item, 'action.sources', []) as $source) {
                    $this->addWebSource($sources, $source);
                }
            }

            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $content .= (string) ($part['text'] ?? '');
                foreach ((array) ($part['annotations'] ?? []) as $annotation) {
                    if (($annotation['type'] ?? null) === 'url_citation') {
                        $this->addWebSource($sources, $annotation);
                    }
                }
            }
        }

        $content = trim($content !== '' ? $content : (string) ($data['output_text'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('OpenAI Responses returned an empty answer.');
        }

        $sources = array_values($sources);
        foreach ($sources as $index => &$source) {
            $source['source_label'] = 'WEB_SOURCE_'.($index + 1);
        }
        unset($source);

        return [
            'content' => $content,
            'sources' => $sources,
            'model' => $model,
            'used_web_search' => $usedWebSearch,
            'usage' => [
                'input_tokens' => data_get($data, 'usage.input_tokens'),
                'output_tokens' => data_get($data, 'usage.output_tokens'),
                'total_tokens' => data_get($data, 'usage.total_tokens'),
            ],
        ];
    }

    private function allowedWebDomains(): array
    {
        $configured = config('ai.openai_web_search_allowed_domains', []);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        return collect(is_array($configured) ? $configured : [])
            ->map(function ($domain): string {
                $domain = trim((string) $domain);
                $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;

                return trim($domain, '/');
            })
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }

    private function addWebSource(array &$sources, array $source): void
    {
        $url = trim((string) ($source['url'] ?? ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $sources[$url] = [
            'source_type' => 'web',
            'title' => mb_substr(trim((string) ($source['title'] ?? 'Web source')), 0, 300),
            'url' => $url,
            'snippet' => mb_substr(trim((string) ($source['snippet'] ?? '')), 0, 1000),
            'content' => mb_substr(trim((string) ($source['snippet'] ?? '')), 0, 1000),
        ];
    }

    private function schemaProperty(mixed $type): array
    {
        if (is_array($type)) {
            return $type;
        }

        return match ((string) $type) {
            'boolean' => ['type' => 'boolean'],
            'number', 'float' => ['type' => 'number'],
            'integer', 'int' => ['type' => 'integer'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'object' => ['type' => 'object', 'additionalProperties' => true],
            default => ['type' => 'string'],
        };
    }

    private function schemaName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($name)) ?: 'structured_response';

        return substr($name, 0, 64);
    }

    private function assertMatchesSchema(mixed $value, array $schema, string $path): void
    {
        $types = (array) ($schema['type'] ?? []);
        if ($types !== [] && ! $this->matchesOneType($value, $types)) {
            throw new RuntimeException(sprintf(
                'OpenAI structured response did not match schema at %s.',
                $path
            ));
        }

        if (isset($schema['enum']) && ! in_array($value, (array) $schema['enum'], true)) {
            throw new RuntimeException(sprintf(
                'OpenAI structured response returned an unsupported value at %s.',
                $path
            ));
        }

        if (in_array('object', $types, true) && is_array($value)) {
            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (! array_key_exists($required, $value)) {
                    throw new RuntimeException(sprintf(
                        'OpenAI structured response omitted required field %s.%s.',
                        $path,
                        $required
                    ));
                }
            }

            foreach ((array) ($schema['properties'] ?? []) as $key => $propertySchema) {
                if (array_key_exists($key, $value) && is_array($propertySchema)) {
                    $this->assertMatchesSchema($value[$key], $propertySchema, $path.'.'.$key);
                }
            }
        }

        if (in_array('array', $types, true) && is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                $this->assertMatchesSchema($item, $schema['items'], $path.'['.$index.']');
            }
        }
    }

    private function matchesOneType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $matches = match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'boolean' => is_bool($value),
                'number' => is_int($value) || is_float($value),
                'integer' => is_int($value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ! array_is_list($value),
                default => true,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * @return array  Float array of the embedding vector.
     *
     * @throws RuntimeException
     */
    public function embedding(string $text): array
    {
        return $this->embeddingMany([$text])[0] ?? [];
    }

    public function embeddingMany(array $texts): array
    {
        $inputs = array_values(array_map(
            fn ($text): string => trim((string) $text),
            $texts
        ));

        if ($inputs === [] || in_array('', $inputs, true)) {
            throw new RuntimeException('Embedding inputs must contain non-empty text.');
        }

        $model = (string) config('ai.embedding_model', 'text-embedding-3-large');
        $expectedDimensions = (int) config('ai.embedding_dimensions', 1536);
        $payload = [
            'model' => $model,
            'input' => $inputs,
            'encoding_format' => 'float',
        ];

        if (str_starts_with($model, 'text-embedding-3') && $expectedDimensions > 0) {
            $payload['dimensions'] = $expectedDimensions;
        }

        $startedAt = microtime(true);

        try {
            $request = Http::withToken($this->apiKey)
                ->acceptJson()
                ->connectTimeout(max(1, (int) config('ai.embedding_connect_timeout', 15)))
                ->timeout(max(5, (int) config('ai.embedding_request_timeout', 90)))
                ->retry(2, 750);

            if (is_string($this->organization) && trim($this->organization) !== '') {
                $request = $request->withHeaders([
                    'OpenAI-Organization' => trim($this->organization),
                ]);
            }

            Log::info('ai.embedding.request_started', [
                'model' => $model,
                'inputs_count' => count($inputs),
                'characters_total' => array_sum(array_map('mb_strlen', $inputs)),
            ]);

            $response = $request->post('https://api.openai.com/v1/embeddings', $payload);

            if ($response->failed()) {
                throw new RuntimeException(sprintf(
                    'OpenAI embedding request failed with HTTP %d: %s',
                    $response->status(),
                    mb_substr((string) $response->body(), 0, 800)
                ));
            }

            $data = $response->json('data');

            if (! is_array($data) || count($data) !== count($inputs)) {
                throw new RuntimeException('OpenAI embedding response returned an unexpected number of vectors.');
            }

            usort($data, fn (array $left, array $right): int => ((int) ($left['index'] ?? 0)) <=> ((int) ($right['index'] ?? 0))
            );

            $embeddings = [];

            foreach ($data as $item) {
                $embedding = $item['embedding'] ?? null;

                if (! is_array($embedding) || $embedding === []) {
                    throw new RuntimeException('OpenAI embedding response contained an empty vector.');
                }

                if ($expectedDimensions > 0 && count($embedding) !== $expectedDimensions) {
                    throw new RuntimeException(sprintf(
                        'Embedding dimension mismatch: expected %d, got %d.',
                        $expectedDimensions,
                        count($embedding)
                    ));
                }

                $embeddings[] = array_map('floatval', $embedding);
            }

            Log::info('ai.embedding.request_completed', [
                'model' => $model,
                'inputs_count' => count($inputs),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $embeddings;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI embedding request failed: '.$e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
}

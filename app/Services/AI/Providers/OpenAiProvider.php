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

    /**
     * Send a chat completion request that returns a JSON object.
     *
     * @param  array  $messages  Array of ['role' => ..., 'content' => ...] messages.
     * @param  array  $schema    Optional schema hint (for documentation purposes).
     * @param  array  $options   Additional options merged into the request payload.
     *
     * @throws RuntimeException
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        try {
            $payload = array_merge([
                'model' => config('ai.chat_model'),
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
            ], $options);

            $response = OpenAI::chat()->create($payload);

            $content = $response->choices[0]->message->content ?? '{}';

            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                throw new RuntimeException(
                    'OpenAI chatJson returned non-array JSON: '.$content
                );
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

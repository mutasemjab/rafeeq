<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class OpenAiProvider implements LlmProviderInterface
{
    public function __construct()
    {
        $apiKey = config('ai.openai_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'OpenAI API key is not configured. Set AI_OPENAI_API_KEY in your .env file.'
            );
        }
    }

    /**
     * Send a chat completion request and return the assistant's text content.
     *
     * @param  array  $messages  Array of ['role' => ..., 'content' => ...] messages.
     * @param  array  $options   Additional options merged into the request payload.
     * @return string
     *
     * @throws RuntimeException
     */
    public function chat(array $messages, array $options = []): string
    {
        try {
            $payload = array_merge([
                'model'    => config('ai.chat_model'),
                'messages' => $messages,
            ], $options);

            $response = OpenAI::chat()->create($payload);

            return $response->choices[0]->message->content ?? '';
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI chat request failed: ' . $e->getMessage(),
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
     * @return array
     *
     * @throws RuntimeException
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        try {
            $payload = array_merge([
                'model'           => config('ai.chat_model'),
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
            ], $options);

            $response = OpenAI::chat()->create($payload);

            $content = $response->choices[0]->message->content ?? '{}';

            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'OpenAI chatJson returned non-array JSON: ' . $content
                );
            }

            return $decoded;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI chatJson request failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * @param  string  $text
     * @return array  Float array of the embedding vector.
     *
     * @throws RuntimeException
     */
    public function embedding(string $text): array
    {
        try {
            $response = OpenAI::embeddings()->create([
                'model' => config('ai.embedding_model'),
                'input' => $text,
            ]);

            $embedding = $response->embeddings[0]->embedding ?? null;

            if (!is_array($embedding)) {
                throw new RuntimeException('OpenAI embedding response did not contain a valid vector.');
            }

            $expectedDimensions = (int) config('ai.embedding_dimensions');

            if ($expectedDimensions > 0 && count($embedding) !== $expectedDimensions) {
                throw new RuntimeException(
                    sprintf(
                        'Embedding dimension mismatch: expected %d, got %d.',
                        $expectedDimensions,
                        count($embedding)
                    )
                );
            }

            return $embedding;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'OpenAI embedding request failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
}

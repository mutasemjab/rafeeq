<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;

class FakeLlmProvider implements LlmProviderInterface
{
    /**
     * Return a fake chat response for testing.
     *
     * @param  array  $messages
     * @param  array  $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string
    {
        return 'This is a fake AI response for testing.';
    }

    /**
     * Return a fake JSON response for testing.
     * Returns ['memories' => []] or an empty array depending on the schema hint.
     *
     * @param  array  $messages
     * @param  array  $schema
     * @param  array  $options
     * @return array
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        // If the schema or messages hint at a memories structure, return the appropriate shape.
        if (isset($schema['memories']) || $this->messagesHintMemories($messages)) {
            return ['memories' => []];
        }

        return [];
    }

    /**
     * Return a fake embedding vector for testing.
     * Produces a vector of the configured dimension filled with 0.01.
     *
     * @param  string  $text
     * @return array
     */
    public function embedding(string $text): array
    {
        $dimensions = (int) config('ai.embedding_dimensions', 1536);

        return array_fill(0, $dimensions, 0.01);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Detect whether any message content references memory extraction.
     *
     * @param  array  $messages
     * @return bool
     */
    private function messagesHintMemories(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            if (stripos($content, 'memor') !== false) {
                return true;
            }
        }

        return false;
    }
}

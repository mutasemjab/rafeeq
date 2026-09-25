<?php

namespace App\Services\AI\Contracts;

interface LlmProviderInterface
{
    public function chat(array $messages, array $options = []): string;

    /**
     * Generate a user-facing answer and return any provider-retrieved sources.
     *
     * @return array{content: string, sources: array, model: string|null, used_web_search: bool, usage: array}
     */
    public function answer(array $messages, array $options = []): array;

    public function chatJson(array $messages, array $schema = [], array $options = []): array;

    public function embedding(string $text): array;

    /**
     * Generate embeddings for multiple texts in one provider request.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embeddingMany(array $texts): array;
}

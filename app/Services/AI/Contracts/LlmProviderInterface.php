<?php

namespace App\Services\AI\Contracts;

interface LlmProviderInterface
{
    public function chat(array $messages, array $options = []): string;

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

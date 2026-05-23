<?php

namespace App\Services\AI\Contracts;

interface LlmProviderInterface
{
    public function chat(array $messages, array $options = []): string;

    public function chatJson(array $messages, array $schema = [], array $options = []): array;

    public function embedding(string $text): array;
}

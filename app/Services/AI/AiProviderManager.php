<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\Providers\FakeLlmProvider;
use App\Services\AI\Providers\OpenAiProvider;
use InvalidArgumentException;

class AiProviderManager
{
    public function driver(?string $name = null): LlmProviderInterface
    {
        return match ($name ?? config('ai.provider', 'openai')) {
            'fake' => app(FakeLlmProvider::class),
            'openai' => app(OpenAiProvider::class),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported AI provider [%s].', $name ?? config('ai.provider'))
            ),
        };
    }
}

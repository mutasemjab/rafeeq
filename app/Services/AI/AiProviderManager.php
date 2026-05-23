<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmProviderInterface;

class AiProviderManager
{
    public function __construct(private LlmProviderInterface $provider)
    {
    }

    public function provider(): LlmProviderInterface
    {
        return $this->provider;
    }
}

<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ChatServiceUnavailableException extends HttpException
{
    public function __construct(
        private string $stage,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct(503, $message, $previous);
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function errorCode(): string
    {
        return match ($this->stage) {
            'domain_guard' => 'AI_DOMAIN_GUARD_UNAVAILABLE',
            'embedding' => 'AI_EMBEDDING_UNAVAILABLE',
            'attachment_search' => 'AI_ATTACHMENT_SEARCH_UNAVAILABLE',
            'knowledge_search' => 'AI_KNOWLEDGE_SEARCH_UNAVAILABLE',
            'answer_generation' => 'AI_ANSWER_UNAVAILABLE',
            default => 'AI_SERVICE_UNAVAILABLE',
        };
    }
}

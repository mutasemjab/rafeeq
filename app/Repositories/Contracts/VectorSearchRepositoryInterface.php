<?php

namespace App\Repositories\Contracts;

interface VectorSearchRepositoryInterface
{
    public function searchKnowledge(array $queryEmbedding, int $limit, float $threshold): array;

    public function searchChatAttachments(array $queryEmbedding, int $userId, int $conversationId, int $limit, float $threshold): array;
}

<?php

namespace App\Repositories\Contracts;

interface VectorSearchRepositoryInterface
{
    public function searchKnowledge(array $queryEmbedding, int $limit, float $threshold): array;

    public function searchKnowledgeMany(array $queryEmbeddings, int $limit, float $threshold): array;

    public function searchChatAttachments(array $queryEmbedding, int $userId, int $conversationId, int $limit, float $threshold): array;

    public function searchChatAttachmentsMany(
        array $queryEmbeddings,
        int $userId,
        int $conversationId,
        int $limit,
        float $threshold
    ): array;
}

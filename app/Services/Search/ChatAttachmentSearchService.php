<?php

namespace App\Services\Search;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;

class ChatAttachmentSearchService
{
    public function __construct(
        private LlmProviderInterface $llm,
        private VectorSearchRepositoryInterface $repo
    ) {
    }

    /**
     * Search chat attachment chunks for a specific user and conversation.
     */
    public function search(int $userId, int $conversationId, string $question, ?int $limit = null): array
    {
        $embedding = $this->llm->embedding($question);

        return $this->searchWithEmbeddings(
            $userId,
            $conversationId,
            [$embedding],
            $limit
        );
    }

    public function searchWithEmbeddings(
        int $userId,
        int $conversationId,
        array $embeddings,
        ?int $limit = null
    ): array {
        $limit = $limit ?? (int) config('ai.max_chat_attachment_chunks');
        $threshold = (float) config('ai.document_similarity_threshold');

        $results = count($embeddings) === 1
            ? $this->repo->searchChatAttachments(
                $embeddings[0],
                $userId,
                $conversationId,
                $limit,
                $threshold
            )
            : $this->repo->searchChatAttachmentsMany(
                $embeddings,
                $userId,
                $conversationId,
                $limit,
                $threshold
            );

        foreach ($results as $i => &$result) {
            $result['source_label'] = 'CHAT_SOURCE_'.($i + 1);
            $result['source_type'] = 'chat_attachment';
            $result['snippet'] = mb_substr($result['content'] ?? '', 0, 200);
        }
        unset($result);

        return $results;
    }
}

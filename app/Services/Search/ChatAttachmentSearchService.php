<?php

namespace App\Services\Search;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;

class ChatAttachmentSearchService
{
    public function __construct(
        private LlmProviderInterface           $llm,
        private VectorSearchRepositoryInterface $repo
    ) {
    }

    /**
     * Search chat attachment chunks for a specific user and conversation.
     *
     * @param  int       $userId
     * @param  int       $conversationId
     * @param  string    $question
     * @param  int|null  $limit
     * @return array
     */
    public function search(int $userId, int $conversationId, string $question, ?int $limit = null): array
    {
        $limit     = $limit ?? (int) config('ai.max_chat_attachment_chunks');
        $threshold = (float) config('ai.document_similarity_threshold');

        $embedding = $this->llm->embedding($question);

        $results = $this->repo->searchChatAttachments($embedding, $userId, $conversationId, $limit, $threshold);

        foreach ($results as $i => &$result) {
            $result['source_label'] = 'CHAT_SOURCE_' . ($i + 1);
            $result['source_type']  = 'chat_attachment';
            $result['snippet']      = substr($result['content'] ?? '', 0, 200);
        }
        unset($result);

        return $results;
    }
}

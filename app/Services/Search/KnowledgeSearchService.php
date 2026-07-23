<?php

namespace App\Services\Search;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;

class KnowledgeSearchService
{
    public function __construct(
        private LlmProviderInterface $llm,
        private VectorSearchRepositoryInterface $repo
    ) {
    }

    /**
     * Search the knowledge base for chunks relevant to a question.
     */
    public function search(string $question, ?int $limit = null): array
    {
        $embedding = $this->llm->embedding($question);

        return $this->searchWithEmbeddings([$embedding], $limit);
    }

    public function searchWithEmbeddings(array $embeddings, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('ai.max_knowledge_chunks');
        $threshold = (float) config('ai.document_similarity_threshold');

        $results = count($embeddings) === 1
            ? $this->repo->searchKnowledge($embeddings[0], $limit, $threshold)
            : $this->repo->searchKnowledgeMany($embeddings, $limit, $threshold);

        foreach ($results as $i => &$result) {
            $result['source_label'] = 'KB_SOURCE_'.($i + 1);
            $result['source_type'] = 'knowledge_base';
            $result['snippet'] = mb_substr($result['content'] ?? '', 0, 200);
        }
        unset($result);

        return $results;
    }
}

<?php

namespace App\Services\Search;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Log;

class KnowledgeSearchService
{
    public function __construct(
        private LlmProviderInterface           $llm,
        private VectorSearchRepositoryInterface $repo
    ) {
    }

    /**
     * Search the knowledge base for chunks relevant to a question.
     *
     * @param  string    $question
     * @param  int|null  $limit
     * @return array
     */
    public function search(string $question, ?int $limit = null): array
    {
        $limit     = $limit ?? (int) config('ai.max_knowledge_chunks');
        $threshold = (float) config('ai.document_similarity_threshold');

        $embedding = $this->llm->embedding($question);

        $results = $this->repo->searchKnowledge($embedding, $limit, $threshold);

        Log::channel('daily')->info('KnowledgeSearch: query completed', [
            'question'       => $question,
            'limit'          => $limit,
            'threshold'      => $threshold,
            'chunks_found'   => count($results),
        ]);

        foreach ($results as $i => &$result) {
            $result['source_label'] = 'KB_SOURCE_' . ($i + 1);
            $result['source_type']  = 'knowledge_base';
            $result['snippet']      = substr($result['content'] ?? '', 0, 200);

            Log::channel('daily')->info('KnowledgeSearch: chunk #' . ($i + 1), [
                'source_label'    => $result['source_label'],
                'document_id'     => $result['knowledge_document_id'] ?? null,
                'similarity'      => $result['similarity'] ?? null,
                'content_preview' => substr($result['content'] ?? '', 0, 300),
            ]);
        }
        unset($result);

        if (empty($results)) {
            Log::channel('daily')->warning('KnowledgeSearch: no chunks found above threshold', [
                'question'  => $question,
                'threshold' => $threshold,
            ]);
        }

        return $results;
    }
}

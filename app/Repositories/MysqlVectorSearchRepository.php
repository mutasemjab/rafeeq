<?php

namespace App\Repositories;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MysqlVectorSearchRepository implements VectorSearchRepositoryInterface
{
    /**
     * Search the system knowledge base using cosine similarity.
     *
     * @param  array  $queryEmbedding
     * @param  int    $limit
     * @param  float  $threshold
     * @return array
     */
    public function searchKnowledge(array $queryEmbedding, int $limit, float $threshold): array
    {
        $rows = DB::table('knowledge_document_chunks as c')
            ->join('knowledge_documents as d', 'd.id', '=', 'c.knowledge_document_id')
            ->where('d.status', 'processed')
            ->whereNull('d.deleted_at')
            ->select([
                'c.id            as chunk_id',
                'd.id            as document_id',
                'd.original_name as document_name',
                'd.title         as title',
                'd.category      as category',
                'c.page_number   as page_number',
                'c.chunk_index   as chunk_index',
                'c.content       as content',
                'c.embedding     as embedding',
                'c.embedding_dimensions as embedding_dimensions',
                'c.metadata      as metadata',
            ])
            ->orderBy('c.id')
            ->cursor();

        $results      = [];
        $nullCount    = 0;
        $belowThresh  = 0;
        $incompatible = 0;
        $topSimilarity = 0.0;
        $totalRows = 0;

        foreach ($rows as $row) {
            $totalRows++;
            $embedding = $this->decodeEmbedding($row->embedding);

            if ($embedding === null) {
                $nullCount++;
                continue;
            }

            if (! $this->isCompatibleEmbedding($row, $embedding, $queryEmbedding)) {
                $incompatible++;
                continue;
            }

            $similarity    = $this->cosine($queryEmbedding, $embedding);
            $topSimilarity = max($topSimilarity, $similarity);

            if ($similarity < $threshold) {
                $belowThresh++;
                continue;
            }

            $results[] = [
                'chunk_id'               => $row->chunk_id,
                'knowledge_document_id'  => $row->document_id,
                'document_name'          => $row->document_name,
                'title'                  => $row->title,
                'category'               => $row->category,
                'page_number'            => $row->page_number,
                'chunk_index'            => $row->chunk_index,
                'content'                => $row->content,
                'similarity'             => $similarity,
            ];

            // Retain only the strongest candidates so a large knowledge base
            // never accumulates every passing row in PHP memory.
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            if (count($results) > $limit) {
                array_pop($results);
            }
        }

        Log::info('[VectorSearch] Knowledge: scoring complete', [
            'total_rows'     => $totalRows,
            'null_embeddings'=> $nullCount,
            'incompatible_embeddings' => $incompatible,
            'below_threshold'=> $belowThresh,
            'passed'         => count($results),
            'top_similarity' => round($topSimilarity, 4),
            'threshold'      => $threshold,
        ]);

        if ($nullCount > 0) {
            Log::warning('[VectorSearch] Knowledge: some chunks had null/invalid embeddings', [
                'null_count' => $nullCount,
            ]);
        }

        if ($incompatible > 0) {
            Log::warning('[VectorSearch] Knowledge: skipped embeddings from a different model or dimension', [
                'incompatible_count' => $incompatible,
                'expected_model' => config('ai.embedding_model'),
                'expected_dimensions' => count($queryEmbedding),
            ]);
        }

        if (count($results) === 0 && $totalRows > 0) {
            Log::warning('[VectorSearch] Knowledge: all chunks below threshold', [
                'top_similarity' => round($topSimilarity, 4),
                'threshold'      => $threshold,
                'suggestion'     => $topSimilarity > 0
                    ? 'Consider lowering document_similarity_threshold in config/ai.php'
                    : 'Embeddings may all be null — check if ProcessKnowledgeDocumentJob ran successfully',
            ]);
        }

        return $results;
    }

    /**
     * Search chat attachment chunks for a specific user and conversation.
     *
     * @param  array  $queryEmbedding
     * @param  int    $userId
     * @param  int    $conversationId
     * @param  int    $limit
     * @param  float  $threshold
     * @return array
     */
    public function searchChatAttachments(
        array $queryEmbedding,
        int   $userId,
        int   $conversationId,
        int   $limit,
        float $threshold
    ): array {
        $rows = DB::table('chat_attachment_chunks as c')
            ->join('chat_attachments as a', 'a.id', '=', 'c.chat_attachment_id')
            ->where('c.user_id', $userId)
            ->where('c.conversation_id', $conversationId)
            ->where('a.status', 'processed')
            ->select([
                'c.id               as chunk_id',
                'a.id               as attachment_id',
                'a.original_name    as original_name',
                'c.page_number      as page_number',
                'c.chunk_index      as chunk_index',
                'c.content          as content',
                'c.embedding        as embedding',
                'c.embedding_dimensions as embedding_dimensions',
                'c.metadata         as metadata',
            ])
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $embedding = $this->decodeEmbedding($row->embedding);

            if ($embedding === null) {
                continue;
            }

            if (! $this->isCompatibleEmbedding($row, $embedding, $queryEmbedding)) {
                continue;
            }

            $similarity = $this->cosine($queryEmbedding, $embedding);

            if ($similarity < $threshold) {
                continue;
            }

            $results[] = [
                'chunk_id'      => $row->chunk_id,
                'attachment_id' => $row->attachment_id,
                'original_name' => $row->original_name,
                'page_number'   => $row->page_number,
                'chunk_index'   => $row->chunk_index,
                'content'       => $row->content,
                'similarity'    => $similarity,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Compute cosine similarity between two vectors.
     *
     * @param  array  $a
     * @param  array  $b
     * @return float  Value in [0, 1] (or 0 if either vector is a zero vector).
     */
    private function cosine(array $a, array $b): float
    {
        if ($a === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot   = 0.0;
        $magA  = 0.0;
        $magB  = 0.0;
        $count = count($a);

        for ($i = 0; $i < $count; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        return $dot / ($magA * $magB);
    }

    /**
     * Decode a raw JSON embedding string into a float array.
     * Returns null if the value is missing or invalid.
     *
     * @param  mixed  $raw
     * @return array|null
     */
    private function decodeEmbedding($raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        // Validate that every element is numeric.
        foreach ($decoded as $value) {
            if (!is_numeric($value)) {
                return null;
            }
        }

        return array_map('floatval', $decoded);
    }

    private function isCompatibleEmbedding(object $row, array $embedding, array $queryEmbedding): bool
    {
        if (
            count($embedding) !== count($queryEmbedding)
            || (int) ($row->embedding_dimensions ?? 0) !== count($queryEmbedding)
        ) {
            return false;
        }

        $metadata = $row->metadata ?? null;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        return is_array($metadata)
            && ($metadata['embedding_model'] ?? null) === (string) config('ai.embedding_model');
    }
}

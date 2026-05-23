<?php

namespace App\Repositories;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use Illuminate\Support\Facades\DB;

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
                'c.id          as chunk_id',
                'd.id          as document_id',
                'd.original_name as document_name',
                'd.title       as title',
                'd.category    as category',
                'c.page_number as page_number',
                'c.chunk_index as chunk_index',
                'c.content     as content',
                'c.embedding   as embedding',
            ])
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $embedding = $this->decodeEmbedding($row->embedding);

            if ($embedding === null) {
                continue;
            }

            $similarity = $this->cosine($queryEmbedding, $embedding);

            if ($similarity < $threshold) {
                continue;
            }

            $results[] = [
                'chunk_id'      => $row->chunk_id,
                'document_id'   => $row->document_id,
                'document_name' => $row->document_name,
                'title'         => $row->title,
                'category'      => $row->category,
                'page_number'   => $row->page_number,
                'chunk_index'   => $row->chunk_index,
                'content'       => $row->content,
                'similarity'    => $similarity,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
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
            ])
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $embedding = $this->decodeEmbedding($row->embedding);

            if ($embedding === null) {
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
        $dot   = 0.0;
        $magA  = 0.0;
        $magB  = 0.0;
        $count = min(count($a), count($b));

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
}

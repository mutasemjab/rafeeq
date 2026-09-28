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

    public function searchWithEmbeddings(array $embeddings, ?int $limit = null, array $filters = []): array
    {
        $limit = $limit ?? (int) config('ai.max_knowledge_chunks');
        $threshold = (float) config('ai.document_similarity_threshold');

        $results = count($embeddings) === 1
            ? $this->repo->searchKnowledge($embeddings[0], $limit, $threshold, $filters)
            : $this->repo->searchKnowledgeMany($embeddings, $limit, $threshold, $filters);

        return $this->label($results);
    }

    /**
     * Retrieve a wider semantic candidate set, then rerank it using lexical and
     * case-metadata matches. This keeps approved evidence primary while still
     * handling natural caregiver language and multi-part questions.
     */
    public function searchForCase(
        array $embeddings,
        array $queries,
        array $filters = [],
        ?int $limit = null
    ): array {
        $limit = $limit ?? (int) config('ai.max_knowledge_chunks');
        $candidateLimit = max($limit, min(40, $limit * 3));
        $threshold = (float) config('ai.document_similarity_threshold');
        $results = $this->repo->searchKnowledgeMany($embeddings, $candidateLimit, $threshold, $filters);
        $terms = $this->queryTerms($queries);

        foreach ($results as &$result) {
            $semantic = max(0.0, min(1.0, (float) ($result['similarity'] ?? 0)));
            $lexical = $this->lexicalScore($terms, (string) ($result['content'] ?? ''));
            $metadata = $this->metadataScore($result, $filters);
            $authority = $this->authorityScore($result);
            $recency = $this->recencyScore($result);
            $language = $this->languageScore($result, $filters);
            $result['retrieval_score'] = round(
                ($semantic * 0.62)
                + ($lexical * 0.16)
                + ($metadata * 0.10)
                + ($authority * 0.06)
                + ($recency * 0.03)
                + ($language * 0.03),
                6
            );
            $result['retrieval_signals'] = [
                'semantic' => round($semantic, 6),
                'lexical' => round($lexical, 6),
                'metadata' => round($metadata, 6),
                'authority' => round($authority, 6),
                'recency' => round($recency, 6),
                'language' => round($language, 6),
            ];
        }
        unset($result);

        usort(
            $results,
            fn (array $left, array $right): int => ($right['retrieval_score'] ?? 0) <=> ($left['retrieval_score'] ?? 0)
        );

        return $this->label(array_slice($results, 0, $limit));
    }

    private function label(array $results): array
    {

        foreach ($results as $i => &$result) {
            $result['source_label'] = 'KB_SOURCE_'.($i + 1);
            $result['source_type'] = 'knowledge_base';
            $result['snippet'] = mb_substr($result['content'] ?? '', 0, 200);
        }
        unset($result);

        return $results;
    }

    private function queryTerms(array $queries): array
    {
        $text = mb_strtolower(implode(' ', array_map('strval', $queries)));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

        return collect($parts)
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->take(80)
            ->values()
            ->all();
    }

    private function lexicalScore(array $terms, string $content): float
    {
        if ($terms === [] || trim($content) === '') {
            return 0.0;
        }

        $content = mb_strtolower($content);
        $matches = collect($terms)->filter(fn (string $term): bool => str_contains($content, $term))->count();

        return min(1.0, $matches / max(1, min(8, count($terms))));
    }

    private function metadataScore(array $result, array $filters): float
    {
        $targets = collect([
            $filters['domain'] ?? null,
            ...($filters['problem_types'] ?? []),
            ...($filters['topics'] ?? []),
        ])->filter()->map(fn ($value): string => mb_strtolower((string) $value))->values();

        if ($targets->isEmpty()) {
            return 0.5;
        }

        $metadata = collect([
            $result['category'] ?? null,
            ...($result['topics'] ?? []),
            ...($result['problem_types'] ?? []),
        ])->filter()->map(fn ($value): string => mb_strtolower((string) $value));

        $matches = $targets->filter(function (string $target) use ($metadata): bool {
            return $metadata->contains(fn (string $value): bool => str_contains($value, $target) || str_contains($target, $value));
        })->count();

        return min(1.0, $matches / max(1, $targets->count()));
    }

    private function authorityScore(array $result): float
    {
        $level = mb_strtolower(trim((string) ($result['evidence_level'] ?? '')));
        $evidence = match (true) {
            str_contains($level, 'systematic'), str_contains($level, 'meta') => 1.0,
            str_contains($level, 'guideline'), str_contains($level, 'clinical'), str_contains($level, 'high') => 0.95,
            str_contains($level, 'peer'), str_contains($level, 'research'), str_contains($level, 'moderate') => 0.78,
            str_contains($level, 'professional'), str_contains($level, 'consensus') => 0.72,
            str_contains($level, 'low'), str_contains($level, 'opinion') => 0.35,
            default => 0.50,
        };
        $provenance = trim((string) ($result['publisher'] ?? '')) !== '' ? 0.65 : 0.35;
        if (filter_var($result['url'] ?? null, FILTER_VALIDATE_URL)) {
            $provenance = min(1.0, $provenance + 0.20);
        }

        return ($evidence * 0.75) + ($provenance * 0.25);
    }

    private function recencyScore(array $result): float
    {
        $date = $result['reviewed_at'] ?? $result['published_at'] ?? null;
        if ($date === null || trim((string) $date) === '') {
            return 0.45;
        }

        try {
            $years = max(0.0, now()->diffInDays(\Carbon\Carbon::parse($date), true) / 365.25);
        } catch (\Throwable) {
            return 0.45;
        }

        return match (true) {
            $years <= 2 => 1.0,
            $years <= 5 => 0.80,
            $years <= 8 => 0.58,
            default => 0.35,
        };
    }

    private function languageScore(array $result, array $filters): float
    {
        $requested = mb_strtolower(trim((string) ($filters['language'] ?? '')));
        $source = mb_strtolower(trim((string) ($result['language'] ?? '')));
        if ($requested === '' || $source === '') {
            return 0.60;
        }
        if ($source === $requested || str_contains($source, 'multi') || str_contains($source, 'bilingual')) {
            return 1.0;
        }

        return 0.45;
    }
}

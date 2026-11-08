<?php

namespace App\Services\Search;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;

class KnowledgeSearchService
{
    public function hasEligibleSources(array $filters): bool
    {
        $query = KnowledgeSourceQuery::make($filters)
            ->where('c.embedding_dimensions', (int) config('ai.embedding_dimensions'));
        return $query->exists();
    }
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
        ?int $limit = null,
        ?float $threshold = null
    ): array {
        $limit = $limit ?? (int) config('ai.max_knowledge_chunks');
        $candidateLimit = max($limit, min(40, $limit * 3));
        $threshold ??= (float) config('ai.document_similarity_threshold');
        $results = $this->repo->searchKnowledgeMany($embeddings, $candidateLimit, $threshold, $filters);
        $terms = $this->queryTerms($queries);

        foreach ($results as &$result) {
            $semantic = max(0.0, min(1.0, (float) ($result['similarity'] ?? 0)));
            $lexical = $this->lexicalScore($terms, ($result['title'] ?? '').' '.($result['content'] ?? ''));
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

        // A translated expansion must not crowd out the user's original
        // question. Reserve evidence for each query before filling by score.
        $selected = [];
        if (count($queries) > 1) {
            $perQuery = max(1, min(2, intdiv($limit, count($queries))));
            foreach (array_keys($queries) as $queryIndex) {
                $added = 0;
                foreach ($results as $result) {
                    if (isset($result['query_similarities'][$queryIndex]) && ! isset($selected[$result['chunk_id']])) {
                        $selected[$result['chunk_id']] = $result;
                        if (++$added >= $perQuery) break;
                    }
                }
            }
        }
        foreach ($results as $result) $selected[$result['chunk_id']] ??= $result;

        return $this->label(array_slice(array_values($selected), 0, $limit));
    }

    /**
     * A second, bounded search before web fallback. Text retrieval is independent
     * of embeddings so an exact book passage cannot be excluded by a low cosine
     * score or an unavailable vector. Adjacent chunks recover split explanations.
     */
    public function searchDeeper(array $embeddings, array $queries, array $filters, array $previous = []): array
    {
        $startedAt = hrtime(true);
        $limit = max(4, min(12, (int) config('ai.max_knowledge_chunks', 8)));
        $semantic = $embeddings === [] ? [] : $this->searchForCase($embeddings, $queries, $filters, $limit * 2,
            min((float) config('ai.document_similarity_threshold', 0.35), 0.20));
        $semanticFinishedAt = hrtime(true);
        $termsByQuery = array_map(fn ($query) => $this->queryTerms([$query]), $queries);
        $lexical = [];
        $scannedChunks = 0;
        if (array_filter($termsByQuery) !== []) {
            $perQuery = array_fill(0, count($termsByQuery), []);
            $this->textQuery($filters)->chunkById(200, function ($rows) use (&$perQuery, &$scannedChunks, $termsByQuery, $limit) {
                foreach ($rows as $row) {
                    $scannedChunks++;
                    $source = (array) $row;
                    // The same Unicode normalization applies to every query.
                    // Normalize each passage once, without changing terms or coverage.
                    $body = $this->normalizeText($source['content']);
                    $title = null;
                    foreach ($termsByQuery as $index => $terms) {
                        $bodyScore = $this->normalizedLexicalScore($terms, $body);
                        if ($bodyScore <= 0) continue;
                        // A matching book title must not give every page the
                        // same rank and hide the page that actually explains it.
                        $title ??= $this->normalizeText($source['title']);
                        $source['lexical_recovery_score'] = 0.85 * $bodyScore
                            + 0.15 * $this->normalizedLexicalScore($terms, $title);
                        $retained = $perQuery[$index];
                        if (count($retained) >= $limit * 2
                            && $source['lexical_recovery_score'] <= $retained[count($retained) - 1]['lexical_recovery_score']) continue;
                        $perQuery[$index][] = $source;
                        usort($perQuery[$index], fn ($a, $b) => $b['lexical_recovery_score'] <=> $a['lexical_recovery_score']);
                        if (count($perQuery[$index]) > $limit * 2) array_pop($perQuery[$index]);
                    }
                }
            }, 'c.id', 'chunk_id');
            for ($i = 0; $i < $limit * 2; $i++) {
                foreach ($perQuery as $candidates) {
                    if (isset($candidates[$i])) $lexical[$candidates[$i]['chunk_id']] ??= $candidates[$i];
                }
            }
            $lexical = array_values($lexical);
        }
        $textFinishedAt = hrtime(true);

        // Give each retrieval method room; do not let high-scoring semantic
        // near-misses crowd out literal matches or already-supported passages.
        $selected = [];
        foreach ($previous as $source) {
            if (($source['covers_question_part'] ?? false) && isset($source['chunk_id'])) {
                $selected[$source['chunk_id']] = $source;
            }
        }
        for ($i = 0; $i < $limit; $i++) {
            foreach ([$lexical, $semantic, $previous] as $group) {
                if (isset($group[$i]['chunk_id'])) $selected[$group[$i]['chunk_id']] ??= $group[$i];
            }
        }
        $anchors = array_slice(array_values($selected), 0, $limit);
        $anchorIds = array_column($anchors, 'chunk_id');
        $neighbours = [];
        foreach (array_slice($anchors, 0, 3) as $source) {
            if (! isset($source['knowledge_document_id'], $source['chunk_index'])) continue;
            $rows = $this->textQuery($filters)->where('d.id', $source['knowledge_document_id'])
                ->whereIn('c.chunk_index', [$source['chunk_index'] - 1, $source['chunk_index'] + 1])
                ->orderBy('c.chunk_index')->get();
            foreach ($rows as $row) {
                if (! in_array($row->chunk_id, $anchorIds)) $neighbours[$row->chunk_id] = (array) $row;
            }
        }
        // Keep the full anchor budget and a small adjacent-text budget. The chat
        // service assesses exactly the subset that will fit the answer context.
        $results = $this->label(array_merge($anchors, array_slice(array_values($neighbours), 0, 4)));
        \Illuminate\Support\Facades\Log::info('ai.knowledge.deep_search_completed', [
            'query_count' => count($queries), 'scanned_text_chunks' => $scannedChunks, 'returned_chunks' => count($results),
            'semantic_ms' => (int) round(($semanticFinishedAt - $startedAt) / 1_000_000),
            'text_ms' => (int) round(($textFinishedAt - $semanticFinishedAt) / 1_000_000),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        return $results;
    }

    private function textQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        return KnowledgeSourceQuery::make($filters)->select([
            'c.id as chunk_id', 'c.chunk_index', 'c.page_number', 'c.content',
            'd.id as knowledge_document_id', 'd.title', 'd.publisher', 'd.source_url as url',
            'd.age_min_months', 'd.age_max_months', 'd.audience', 'd.language',
            'd.evidence_level', 'd.published_at', 'd.reviewed_at',
        ]);
    }

    /**
     * Find an actual definition in an approved book before paying for an
     * embedding search. Ambiguous or compound questions use normal retrieval.
     */
    public function findDefinition(array $queries): array
    {
        $prefix = '(?:ماذا يعني|ما معنى|ما هو|ما هي|(?:إيش|ايش|شو|وش|إيه|ايه) يعني|يعني (?:إيه|ايه)|what (?:is|are)|define)';
        $term = '([\p{L}\p{M}]+(?:[ -][\p{L}\p{M}]+){0,5})';
        if (!preg_match("~^$prefix\\s+{$term}[؟?.!]*$~iu", trim($queries[0] ?? ''), $match)) return [];
        $subjects = [$match[1]];
        foreach (array_slice($queries, 1, 2) as $query) {
            $query = preg_replace('~^(?:what is|definition of)\s+|\s+(?:definition|overview)[?.]?$~i', '', trim($query));
            if (preg_match("~^{$term}[?.]?$~u", $query, $match)) $subjects[] = $match[1];
        }
        foreach (array_unique($subjects) as $subject) {
            $docs = \Illuminate\Support\Facades\DB::table('knowledge_document_chunks as c')
                ->join('knowledge_documents as d', 'd.id', '=', 'c.knowledge_document_id')
                ->whereNull('d.deleted_at')->where('d.status', 'processed')->where('d.is_approved', true)
                ->where(function ($q) use ($subject) {
                    $q->where('d.title', 'like', '%'.$subject.'%')
                        ->orWhere('c.content', 'like', '%'.$subject.' is %')
                        ->orWhere('c.content', 'like', '%'.$subject.' refers to %')
                        ->orWhere('c.content', 'like', '%'.$subject.' هو %')
                        ->orWhere('c.content', 'like', '%'.$subject.' هي %');
                })->select(['d.id as knowledge_document_id','d.title','d.publisher','d.source_url as url',
                    'd.evidence_level','d.language','d.age_min_months','d.age_max_months',
                    'c.id as chunk_id','c.page_number','c.content'])
                ->orderBy('c.chunk_index')->orderBy('c.id')->limit(12)->get();

            foreach ($docs as $row) {
                $text = preg_replace('/\s+/u', ' ', $row->content);
                $quoted = preg_quote($subject, '~');
                $pattern = "~(?<![\\p{L}\\p{M}])$quoted\\s+(?:is (?:a|an|the) |refers to |هو |هي )~iu";
                if (!preg_match($pattern, $text, $offset, PREG_OFFSET_CAPTURE)) {
                    if (mb_stripos($row->title, $subject) === false || mb_stripos($text, $subject) === false
                        || !preg_match('~(?:التعريف|ما هو\s*؟|definition\s*:)~iu', $text, $offset, PREG_OFFSET_CAPTURE)) continue;
                }
                $hit = (array) $row;
                // PCRE returns a byte offset. Cut at the match's UTF-8 boundary.
                $hit['content'] = trim(mb_substr(substr($text, $offset[0][1]), 0, (int)config('ai.max_source_context_chars', 1800)));
                if (mb_strlen($hit['content']) < 80) continue;
                $hit['definition_match'] = true;
                return $this->label([$hit]);
            }
        }
        return [];
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
        $text = $this->normalizeText(implode(' ', array_map('strval', $queries)));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

        return collect($parts)
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->reject(fn (string $term): bool => in_array($term, ['the', 'and', 'for', 'what', 'how', 'are', 'from', 'with', 'that', 'which', 'give', 'على', 'علي', 'التي', 'الذي', 'وما', 'كيف', 'عند', 'هذه', 'هذا', 'المذكورة'], true))
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

        return $this->normalizedLexicalScore($terms, $this->normalizeText($content));
    }

    private function normalizedLexicalScore(array $terms, string $content): float
    {
        if ($terms === [] || $content === '') return 0.0;
        $denominator = min(8, count($terms));
        $matches = 0;
        foreach ($terms as $term) {
            if (str_contains($content, $term) && ++$matches >= $denominator) return 1.0;
        }

        return $matches / $denominator;
    }

    private function normalizeText(string $text): string
    {
        if (class_exists(\Normalizer::class)) $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $text);
        return strtr(mb_strtolower($text), ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي']);
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

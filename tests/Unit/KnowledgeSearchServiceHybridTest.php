<?php

namespace Tests\Unit;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\KnowledgeSearchService;
use Mockery;
use Tests\TestCase;

class KnowledgeSearchServiceHybridTest extends TestCase
{
    public function test_each_query_reserves_its_own_best_match_when_candidates_overlap(): void
    {
        $repo = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repo->shouldReceive('searchKnowledgeMany')->once()->andReturn([
            ['chunk_id' => 1, 'content' => 'Original question answer', 'similarity' => 0.75, 'query_similarities' => [0 => 0.75, 1 => 0.3]],
            ['chunk_id' => 2, 'content' => 'Expansion answer', 'similarity' => 0.95, 'query_similarities' => [0 => 0.4, 1 => 0.95]],
            ['chunk_id' => 3, 'content' => 'Expansion background', 'similarity' => 0.94, 'query_similarities' => [0 => 0.35, 1 => 0.94]],
        ]);
        $hits = (new KnowledgeSearchService(Mockery::mock(LlmProviderInterface::class), $repo))
            ->searchForCase([[1, 0], [0, 1]], ['سؤال', 'translated'], [], 2);
        $this->assertSame([1, 2], array_column($hits, 'chunk_id'));
    }

    public function test_translation_cannot_crowd_out_the_original_question(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $repo = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repo->shouldReceive('searchKnowledgeMany')->once()->andReturn([
            ['chunk_id' => 1, 'content' => 'Arabic source', 'similarity' => 0.45, 'query_similarities' => [0 => 0.45]],
            ['chunk_id' => 2, 'content' => 'English expansion', 'similarity' => 0.95, 'query_similarities' => [1 => 0.95]],
            ['chunk_id' => 3, 'content' => 'Another English result', 'similarity' => 0.94, 'query_similarities' => [1 => 0.94]],
        ]);
        $hits = (new KnowledgeSearchService($llm, $repo))->searchForCase([[1, 0], [0, 1]], ['سؤال', 'translated'], [], 2);
        $this->assertSame([1, 2], array_column($hits, 'chunk_id'));
    }
    public function test_hybrid_reranking_uses_query_words_and_case_metadata(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $repository = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repository->shouldReceive('searchKnowledgeMany')
            ->once()
            ->withArgs(function (array $embeddings, int $limit, float $threshold, array $filters): bool {
                return $embeddings === [[1.0, 0.0]]
                    && $limit === 6
                    && $filters['age_months'] === 60
                    && $filters['domain'] === 'behavior';
            })
            ->andReturn([
                [
                    'chunk_id' => 1,
                    'content' => 'General parenting information.',
                    'similarity' => 0.80,
                    'category' => 'general',
                    'topics' => [],
                    'problem_types' => [],
                ],
                [
                    'chunk_id' => 2,
                    'content' => 'Observe the tantrum antecedent and consequence before selecting support.',
                    'similarity' => 0.75,
                    'category' => 'behavior',
                    'topics' => ['behavior'],
                    'problem_types' => ['tantrum'],
                ],
            ]);

        $results = (new KnowledgeSearchService($llm, $repository))->searchForCase(
            [[1.0, 0.0]],
            ['tantrum antecedent consequence'],
            ['age_months' => 60, 'domain' => 'behavior', 'problem_types' => ['tantrum']],
            2
        );

        $this->assertSame(2, $results[0]['chunk_id']);
        $this->assertSame('KB_SOURCE_1', $results[0]['source_label']);
        $this->assertArrayHasKey('retrieval_signals', $results[0]);
        $this->assertArrayHasKey('authority', $results[0]['retrieval_signals']);
        $this->assertArrayHasKey('recency', $results[0]['retrieval_signals']);
        $this->assertArrayHasKey('language', $results[0]['retrieval_signals']);
    }
}

<?php

namespace Tests\Unit;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\KnowledgeSearchService;
use Mockery;
use Tests\TestCase;

class KnowledgeSearchServiceHybridTest extends TestCase
{
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
    }
}

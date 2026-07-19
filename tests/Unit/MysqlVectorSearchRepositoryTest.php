<?php

namespace Tests\Unit;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Repositories\MysqlVectorSearchRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MysqlVectorSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_search_path_retains_only_the_best_requested_results(): void
    {
        Config::set('ai.embedding_model', 'test-embedding-model');
        $document = KnowledgeDocument::create([
            'title' => 'Search Guide',
            'original_name' => 'search.txt',
            'file_path' => 'knowledge/search.txt',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        foreach ([[1.0, 0.0], [0.8, 0.2], [0.0, 1.0]] as $index => $embedding) {
            KnowledgeDocumentChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => 'Chunk '.$index,
                'embedding' => json_encode($embedding),
                'embedding_dimensions' => 2,
                'metadata' => ['embedding_model' => 'test-embedding-model'],
            ]);
        }

        $results = (new MysqlVectorSearchRepository())->searchKnowledge([1.0, 0.0], 2, 0.0);

        $this->assertCount(2, $results);
        $this->assertSame(0, $results[0]['chunk_index']);
        $this->assertSame(1, $results[1]['chunk_index']);
    }

    public function test_it_skips_vectors_from_a_different_model_or_dimension(): void
    {
        Config::set('ai.embedding_model', 'current-model');
        $document = KnowledgeDocument::create([
            'title' => 'Mixed Index',
            'original_name' => 'mixed.txt',
            'file_path' => 'knowledge/mixed.txt',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        foreach ([
            ['vector' => [1.0, 0.0], 'dimensions' => 2, 'model' => 'old-model'],
            ['vector' => [1.0, 0.0, 0.0], 'dimensions' => 3, 'model' => 'current-model'],
        ] as $index => $fixture) {
            KnowledgeDocumentChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => 'Chunk '.$index,
                'embedding' => json_encode($fixture['vector']),
                'embedding_dimensions' => $fixture['dimensions'],
                'metadata' => ['embedding_model' => $fixture['model']],
            ]);
        }

        $results = (new MysqlVectorSearchRepository())->searchKnowledge([1.0, 0.0], 5, 0.0);

        $this->assertSame([], $results);
    }
}

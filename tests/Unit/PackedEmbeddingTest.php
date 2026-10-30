<?php

namespace Tests\Unit;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Repositories\MysqlVectorSearchRepository;
use App\Services\Search\PackedEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackedEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalization_is_bounded_and_rejects_invalid_vectors(): void
    {
        $binary = PackedEmbedding::encode('[3,4]', 2);
        $this->assertSame(8, strlen($binary));
        $vector = PackedEmbedding::decode($binary, 2);
        $this->assertEqualsWithDelta(0.6, $vector[0], 0.000001);
        $this->assertEqualsWithDelta(0.8, $vector[1], 0.000001);
        $this->assertNull(PackedEmbedding::encode('[0,0]', 2));
        $this->assertNull(PackedEmbedding::encode('[1,"bad"]', 2));
        $this->assertNull(PackedEmbedding::encode('[1,2]', 3));
        $this->assertNull(PackedEmbedding::decode($binary, 3));
    }

    public function test_packed_search_matches_legacy_json_and_updates_with_reembedding(): void
    {
        config(['ai.embedding_model' => 'test-model']);
        $d = KnowledgeDocument::create(['title' => 'Fixture', 'original_name' => 'fixture.txt', 'file_path' => 'fixture.txt', 'status' => 'processed']);
        $c = KnowledgeDocumentChunk::create(['knowledge_document_id' => $d->id, 'chunk_index' => 0, 'content' => 'Evidence',
            'embedding' => '[3,4]', 'embedding_dimensions' => 2, 'metadata' => ['embedding_model' => 'test-model']]);
        $repo = new MysqlVectorSearchRepository();
        $packed = $repo->searchKnowledge([1.0, 0.0], 2, 0.1);
        DB::table('knowledge_document_chunks')->where('id', $c->id)->update(['embedding_vector' => null]);
        $legacy = $repo->searchKnowledge([1.0, 0.0], 2, 0.1);
        $this->assertEqualsWithDelta($legacy[0]['similarity'], $packed[0]['similarity'], 0.000001);
        $this->artisan('knowledge:optimize-search')->assertExitCode(0);
        $this->assertNotNull($c->fresh()->embedding_vector);
        $c->update(['embedding' => '[0,1]']);
        $this->assertSame([], $repo->searchKnowledge([1.0, 0.0], 2, 0.1));
    }
}

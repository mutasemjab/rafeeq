<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KnowledgeBundleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_exported_embeddings_can_be_imported_without_api_calls(): void
    {
        Config::set('ai.embedding_model', 'text-embedding-3-small');
        Config::set('ai.embedding_dimensions', 3);
        $bundle = sys_get_temp_dir().'/rafeeq-kb-'.bin2hex(random_bytes(6)).'.ndjson.gz';
        $hash = hash('sha256', 'knowledge-source');

        $document = KnowledgeDocument::create([
            'title' => 'Speech Guide',
            'original_name' => 'speech-guide.txt',
            'file_path' => 'knowledge/speech-guide.txt',
            'mime_type' => 'text/plain',
            'file_size' => 100,
            'content_hash' => $hash,
            'source_path' => 'library/speech-guide.txt',
            'category' => 'Speech',
            'status' => 'processed',
            'processed_at' => now(),
        ]);
        KnowledgeDocumentChunk::create([
            'knowledge_document_id' => $document->id,
            'chunk_index' => 0,
            'page_number' => 1,
            'content' => 'Speech and language knowledge.',
            'token_count' => 5,
            'embedding' => json_encode([0.1, 0.2, 0.3]),
            'embedding_dimensions' => 3,
            'metadata' => [
                'source' => 'test',
                'embedding_model' => 'text-embedding-3-small',
            ],
        ]);

        try {
            $this->artisan('knowledge:export', ['output' => $bundle])->assertExitCode(0);
            $document->forceDelete();
            $this->assertDatabaseCount('knowledge_documents', 0);

            $this->artisan('knowledge:import-index', ['bundle' => $bundle])->assertExitCode(0);
            $this->assertDatabaseHas('knowledge_documents', [
                'content_hash' => $hash,
                'status' => 'processed',
                'index_only' => true,
            ]);
            $this->assertDatabaseHas('knowledge_document_chunks', [
                'chunk_index' => 0,
                'embedding_dimensions' => 3,
            ]);
        } finally {
            @unlink($bundle);
            @unlink($bundle.'.sha256');
        }
    }
}

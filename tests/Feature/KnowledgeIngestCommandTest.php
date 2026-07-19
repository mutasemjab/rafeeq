<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeIngestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recursively_deduplicates_and_skips_metadata_files(): void
    {
        Storage::fake('local');
        $directory = $this->tempDirectory();

        try {
            mkdir($directory.'/nested', 0777, true);
            file_put_contents($directory.'/guide.txt', 'Useful speech and language therapy knowledge.');
            file_put_contents($directory.'/nested/copy.txt', 'Useful speech and language therapy knowledge.');
            file_put_contents($directory.'/nested/.DS_Store', 'junk');
            file_put_contents($directory.'/nested/unsupported.bin', 'binary');

            $this->artisan('knowledge:ingest', [
                'path' => $directory,
                '--category' => 'Speech',
                '--register-only' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseCount('knowledge_documents', 1);
            $this->assertDatabaseHas('knowledge_documents', [
                'category' => 'Speech',
                'status' => 'uploaded',
            ]);
            $document = KnowledgeDocument::firstOrFail();
            $this->assertSame(64, strlen((string) $document->content_hash));
            Storage::disk('local')->assertExists($document->file_path);
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function test_inline_processing_creates_embeddings_and_is_resumable(): void
    {
        Storage::fake('local');
        Config::set('ai.provider', 'fake');
        Config::set('ai.embedding_dimensions', 3);
        Config::set('ai.embedding_batch_size', 2);
        $directory = $this->tempDirectory();

        try {
            file_put_contents($directory.'/guide.txt', str_repeat(
                'Language assessment and evidence based intervention guidance. ',
                180
            ));

            $arguments = [
                'path' => $directory,
                '--category' => 'Speech',
                '--process' => true,
            ];
            $this->artisan('knowledge:ingest', $arguments)->assertExitCode(0);
            $document = KnowledgeDocument::firstOrFail();
            $firstChunkCount = $document->chunks()->count();

            $this->assertSame('processed', $document->status);
            $this->assertGreaterThan(1, $firstChunkCount);
            $this->assertSame(3, (int) $document->chunks()->firstOrFail()->embedding_dimensions);

            $this->artisan('knowledge:ingest', $arguments)->assertExitCode(0);
            $this->assertSame($firstChunkCount, $document->chunks()->count());
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function test_replacing_a_source_file_updates_the_existing_document(): void
    {
        Storage::fake('local');
        $directory = $this->tempDirectory();

        try {
            $path = $directory.'/locked-source.txt';
            file_put_contents($path, 'Original source content.');
            $arguments = [
                'path' => $directory,
                '--category' => 'Speech',
                '--register-only' => true,
            ];

            $this->artisan('knowledge:ingest', $arguments)->assertExitCode(0);
            $document = KnowledgeDocument::firstOrFail();
            $originalHash = $document->content_hash;

            file_put_contents($path, 'Unlocked replacement source content.');
            $this->artisan('knowledge:ingest', $arguments)->assertExitCode(0);

            $document->refresh();
            $this->assertDatabaseCount('knowledge_documents', 1);
            $this->assertNotSame($originalHash, $document->content_hash);
            $this->assertSame('uploaded', $document->status);
            $this->assertSame(
                'Unlocked replacement source content.',
                Storage::disk('local')->get($document->file_path)
            );
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function test_reembed_refreshes_the_model_without_reextracting_or_duplicating_chunks(): void
    {
        Storage::fake('local');
        Config::set('ai.provider', 'fake');
        Config::set('ai.embedding_dimensions', 3);
        Config::set('ai.embedding_model', 'old-model');
        $directory = $this->tempDirectory();

        try {
            file_put_contents($directory.'/guide.txt', str_repeat(
                'Speech therapy and child communication guidance. ',
                120
            ));
            $arguments = [
                'path' => $directory,
                '--category' => 'Speech',
                '--process' => true,
            ];

            $this->artisan('knowledge:ingest', $arguments)->assertExitCode(0);
            $document = KnowledgeDocument::firstOrFail();
            $chunkCount = $document->chunks()->count();
            $this->assertSame('old-model', $document->chunks()->firstOrFail()->metadata['embedding_model']);

            Config::set('ai.embedding_model', 'new-model');
            $this->artisan('knowledge:ingest', $arguments + ['--reembed' => true])
                ->assertExitCode(0);

            $document->refresh();
            $this->assertDatabaseCount('knowledge_documents', 1);
            $this->assertSame($chunkCount, $document->chunks()->count());
            $this->assertSame('new-model', $document->chunks()->firstOrFail()->metadata['embedding_model']);
            $this->assertFalse((bool) $document->ingestion_metadata['embedding_refresh_in_progress']);
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    private function tempDirectory(): string
    {
        $path = sys_get_temp_dir().'/rafeeq-kb-'.bin2hex(random_bytes(6));
        mkdir($path, 0777, true);
        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}

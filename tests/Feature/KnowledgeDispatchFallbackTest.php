<?php

namespace Tests\Feature;

use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class KnowledgeDispatchFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_jobs_table_is_available_for_database_queue_dispatches(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
    }

    public function test_dispatch_with_sync_fallback_processes_document_when_queue_dispatch_fails(): void
    {
        Config::set('ai.embedding_dimensions', 2);

        Storage::disk('public')->put('knowledge/fallback-queue-guide.pdf', 'placeholder document contents');

        $document = KnowledgeDocument::query()->create([
            'title'         => 'Fallback Queue Guide',
            'category'      => 'Recovery',
            'file_path'     => 'knowledge/fallback-queue-guide.pdf',
            'original_name' => 'fallback-queue-guide.pdf',
            'mime_type'     => 'application/pdf',
            'file_size'     => 128,
            'status'        => 'uploaded',
        ]);

        $this->app->bind(DocumentTextExtractor::class, fn () => new class
        {
            public function extractFromStoragePath(string $filePath, ?string $mimeType = null): array
            {
                return [['page' => 1, 'text' => 'Fallback queue recovery content']];
            }
        });

        $this->app->bind(TextChunker::class, fn () => new class
        {
            public function chunk(string|array $text, array $options = []): array
            {
                $content = is_array($text)
                    ? implode(' ', array_column($text, 'text'))
                    : $text;

                return [['content' => $content]];
            }
        });

        $this->app->bind(LlmProviderInterface::class, fn () => new class implements LlmProviderInterface
        {
            public function chat(array $messages, array $options = []): string
            {
                return 'ok';
            }

            public function chatJson(array $messages, array $schema = [], array $options = []): array
            {
                return [];
            }

            public function embedding(string $text): array
            {
                return [0.1, 0.2];
            }

            public function embeddingMany(array $texts): array
            {
                return array_map(fn(): array => [0.1, 0.2], $texts);
            }
        });

        $dispatcher = Mockery::mock(Dispatcher::class);

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(ProcessKnowledgeDocumentJob::class))
            ->andThrow(new RuntimeException('Base table or view not found: jobs'));

        $dispatcher->shouldReceive('dispatchSync')
            ->once()
            ->with(Mockery::type(ProcessKnowledgeDocumentJob::class))
            ->andReturnUsing(function (ProcessKnowledgeDocumentJob $job) {
                app()->call([$job, 'handle']);

                return null;
            });

        $this->app->instance(Dispatcher::class, $dispatcher);

        ProcessKnowledgeDocumentJob::dispatchWithSyncFallback($document->id);

        $this->assertDatabaseHas('knowledge_documents', [
            'id'     => $document->id,
            'status' => 'processed',
        ]);

        $this->assertDatabaseCount('knowledge_document_chunks', 1);
    }
}

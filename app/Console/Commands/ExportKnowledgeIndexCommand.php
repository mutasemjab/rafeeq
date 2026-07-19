<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ExportKnowledgeIndexCommand extends Command
{
    protected $signature = 'knowledge:export
        {output? : Output .ndjson.gz bundle path}
        {--category= : Export only this category}';

    protected $description = 'Export processed knowledge documents and embeddings as a deployable gzip bundle';

    public function handle(): int
    {
        $output = $this->resolveOutputPath((string) ($this->argument('output') ?: ''));
        $directory = dirname($output);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->error("Unable to create export directory: {$directory}");
            return self::FAILURE;
        }

        $documents = KnowledgeDocument::query()
            ->where('status', 'processed')
            ->whereHas('chunks')
            ->when($this->option('category'), fn($query, $category) =>
                $query->where('category', $category)
            )
            ->orderBy('id');
        $documentIds = (clone $documents)->pluck('id');
        $documentCount = $documentIds->count();
        $chunkCount = KnowledgeDocumentChunk::query()
            ->whereIn('knowledge_document_id', $documentIds)
            ->count();

        if ($documentCount === 0) {
            $this->error('There are no processed knowledge documents to export.');
            return self::FAILURE;
        }

        $handle = gzopen($output, 'wb9');
        if ($handle === false) {
            $this->error("Unable to open export bundle: {$output}");
            return self::FAILURE;
        }

        try {
            $expectedModel = (string) config('ai.embedding_model');
            $expectedDimensions = (int) config('ai.embedding_dimensions');

            $this->writeLine($handle, [
                'type' => 'manifest',
                'bundle_version' => 1,
                'created_at' => now()->toIso8601String(),
                'embedding_model' => $expectedModel,
                'embedding_dimensions' => $expectedDimensions,
                'document_count' => $documentCount,
                'chunk_count' => $chunkCount,
                'category_filter' => $this->option('category'),
            ]);

            $exportedDocuments = 0;
            $exportedChunks = 0;

            foreach ($documents->cursor() as $document) {
                $this->writeLine($handle, [
                    'type' => 'document',
                    'source_document_id' => $document->id,
                    'content_hash' => $document->content_hash,
                    'title' => $document->title,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'source_path' => $document->source_path,
                    'category' => $document->category,
                    'ingestion_metadata' => $document->ingestion_metadata,
                    'processed_at' => $document->processed_at?->toIso8601String(),
                ]);
                $exportedDocuments++;

                foreach ($document->chunks()->orderBy('chunk_index')->cursor() as $chunk) {
                    $embedding = json_decode((string) $chunk->embedding, true);
                    $storedModel = $chunk->metadata['embedding_model'] ?? null;
                    if (
                        ! is_array($embedding)
                        || count($embedding) !== $expectedDimensions
                        || (int) $chunk->embedding_dimensions !== $expectedDimensions
                        || $storedModel !== $expectedModel
                    ) {
                        throw new RuntimeException(sprintf(
                            'Chunk #%d is not embedded with %s at %d dimensions.',
                            $chunk->id,
                            $expectedModel,
                            $expectedDimensions
                        ));
                    }

                    $this->writeLine($handle, [
                        'type' => 'chunk',
                        'source_document_id' => $document->id,
                        'chunk_index' => $chunk->chunk_index,
                        'page_number' => $chunk->page_number,
                        'section_title' => $chunk->section_title,
                        'content' => $chunk->content,
                        'token_count' => $chunk->token_count,
                        'metadata' => $chunk->metadata,
                        'embedding' => $embedding,
                        'embedding_dimensions' => $chunk->embedding_dimensions,
                    ]);
                    $exportedChunks++;
                }
            }
        } catch (Throwable $exception) {
            gzclose($handle);
            @unlink($output);
            $this->error('Export failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        gzclose($handle);
        $checksum = hash_file('sha256', $output);
        file_put_contents($output.'.sha256', $checksum.'  '.basename($output).PHP_EOL);

        $this->info("Exported {$exportedDocuments} documents and {$exportedChunks} chunks.");
        $this->line("Bundle: {$output}");
        $this->line("SHA-256: {$checksum}");

        return self::SUCCESS;
    }

    private function writeLine($handle, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false || gzwrite($handle, $json."\n") === false) {
            throw new RuntimeException('Unable to write the knowledge export bundle.');
        }
    }

    private function resolveOutputPath(string $requested): string
    {
        if ($requested === '') {
            return storage_path('app/knowledge_exports/knowledge-base.ndjson.gz');
        }

        return str_starts_with($requested, DIRECTORY_SEPARATOR)
            ? $requested
            : base_path($requested);
    }
}

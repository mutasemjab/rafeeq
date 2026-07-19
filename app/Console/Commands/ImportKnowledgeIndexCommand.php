<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ImportKnowledgeIndexCommand extends Command
{
    protected $signature = 'knowledge:import-index
        {bundle : Exported .ndjson.gz knowledge bundle}
        {--force : Replace chunks for documents already present}';

    protected $description = 'Import a pre-embedded knowledge bundle without making OpenAI API calls';

    public function handle(): int
    {
        $bundle = realpath((string) $this->argument('bundle'));
        if ($bundle === false || !is_file($bundle) || !is_readable($bundle)) {
            $this->error('Knowledge bundle not found or unreadable.');
            return self::FAILURE;
        }

        $handle = gzopen($bundle, 'rb');
        if ($handle === false) {
            $this->error('Unable to open the compressed knowledge bundle.');
            return self::FAILURE;
        }

        $lineNumber = 0;
        $manifest = null;
        $currentDocument = null;
        $currentSourceId = null;
        $documentsImported = 0;
        $chunksImported = 0;

        try {
            while (!gzeof($handle)) {
                $line = gzgets($handle);
                if ($line === false || trim($line) === '') {
                    continue;
                }
                $lineNumber++;
                $record = json_decode($line, true);
                if (!is_array($record)) {
                    throw new RuntimeException("Invalid JSON at bundle line {$lineNumber}.");
                }

                if ($lineNumber === 1) {
                    if (($record['type'] ?? null) !== 'manifest' || (int) ($record['bundle_version'] ?? 0) !== 1) {
                        throw new RuntimeException('Unsupported or missing knowledge bundle manifest.');
                    }
                    $manifest = $record;
                    $this->validateManifest($manifest);
                    continue;
                }

                if (($record['type'] ?? null) === 'document') {
                    if ($currentDocument !== null) {
                        $this->markProcessed($currentDocument);
                    }
                    $currentSourceId = (int) ($record['source_document_id'] ?? 0);
                    $currentDocument = $this->upsertDocument($record, (bool) $this->option('force'));
                    $documentsImported++;
                    continue;
                }

                if (($record['type'] ?? null) === 'chunk') {
                    if ($currentDocument === null || (int) ($record['source_document_id'] ?? 0) !== $currentSourceId) {
                        throw new RuntimeException("Chunk at line {$lineNumber} does not follow its document.");
                    }
                    $this->upsertChunk($currentDocument, $record, (int) $manifest['embedding_dimensions']);
                    $chunksImported++;
                    continue;
                }

                throw new RuntimeException("Unknown record type at bundle line {$lineNumber}.");
            }

            if ($currentDocument !== null) {
                $this->markProcessed($currentDocument);
            }
        } catch (Throwable $exception) {
            if ($currentDocument !== null) {
                $currentDocument->update([
                    'status' => 'failed',
                    'processing_error' => mb_substr($exception->getMessage(), 0, 60000),
                ]);
            }
            gzclose($handle);
            $this->error('Import failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        gzclose($handle);
        $this->info("Imported {$documentsImported} documents and {$chunksImported} chunks without API calls.");

        if ($documentsImported !== (int) ($manifest['document_count'] ?? -1) || $chunksImported !== (int) ($manifest['chunk_count'] ?? -1)) {
            $this->warn('Imported counts differ from the bundle manifest; review the bundle before deployment.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function validateManifest(array $manifest): void
    {
        $bundleModel = (string) ($manifest['embedding_model'] ?? '');
        $bundleDimensions = (int) ($manifest['embedding_dimensions'] ?? 0);
        $configuredModel = (string) config('ai.embedding_model');
        $configuredDimensions = (int) config('ai.embedding_dimensions');

        if ($bundleModel !== $configuredModel || $bundleDimensions !== $configuredDimensions) {
            throw new RuntimeException(sprintf(
                'Embedding configuration mismatch. Bundle uses %s/%d; server uses %s/%d. Set the server to the bundle values before importing.',
                $bundleModel,
                $bundleDimensions,
                $configuredModel,
                $configuredDimensions
            ));
        }
    }

    private function upsertDocument(array $record, bool $force): KnowledgeDocument
    {
        $hash = trim((string) ($record['content_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $hash = hash('sha256', implode('|', [
                $record['original_name'] ?? '',
                $record['source_path'] ?? '',
                $record['source_document_id'] ?? '',
            ]));
        }

        $document = KnowledgeDocument::withTrashed()->where('content_hash', $hash)->first();
        if ($document !== null && $document->trashed()) {
            $document->restore();
        }
        $document ??= new KnowledgeDocument();

        if ($document->exists && $force) {
            $document->chunks()->delete();
        }

        $document->fill([
            'content_hash' => $hash,
            'title' => (string) ($record['title'] ?? $record['original_name'] ?? 'Knowledge document'),
            'original_name' => (string) ($record['original_name'] ?? 'knowledge-document'),
            'file_path' => $document->exists
                ? $document->file_path
                : 'knowledge/index-only/'.substr($hash, 0, 2).'/'.$hash.'.index',
            'mime_type' => $record['mime_type'] ?? 'application/x-knowledge-index',
            'file_size' => $record['file_size'] ?? null,
            'source_path' => $record['source_path'] ?? null,
            'category' => $record['category'] ?? 'general',
            'ingestion_metadata' => array_merge(
                is_array($record['ingestion_metadata'] ?? null) ? $record['ingestion_metadata'] : [],
                ['imported_from_bundle' => true]
            ),
            'index_only' => $document->exists ? $document->index_only : true,
            'status' => 'processing',
            'processing_error' => null,
            'processed_at' => null,
            'uploaded_by' => null,
        ]);
        $document->save();

        return $document;
    }

    private function upsertChunk(KnowledgeDocument $document, array $record, int $dimensions): void
    {
        $embedding = $record['embedding'] ?? null;
        if (!is_array($embedding) || count($embedding) !== $dimensions) {
            throw new RuntimeException(sprintf(
                'Document %d chunk %d has invalid embedding dimensions.',
                $document->id,
                (int) ($record['chunk_index'] ?? -1)
            ));
        }

        KnowledgeDocumentChunk::updateOrCreate(
            [
                'knowledge_document_id' => $document->id,
                'chunk_index' => (int) $record['chunk_index'],
            ],
            [
                'page_number' => $record['page_number'] ?? null,
                'section_title' => $record['section_title'] ?? null,
                'content' => (string) ($record['content'] ?? ''),
                'token_count' => $record['token_count'] ?? null,
                'metadata' => is_array($record['metadata'] ?? null) ? $record['metadata'] : null,
                'embedding' => json_encode($embedding, JSON_PRESERVE_ZERO_FRACTION),
                'embedding_dimensions' => $dimensions,
            ]
        );
    }

    private function markProcessed(KnowledgeDocument $document): void
    {
        if (!$document->chunks()->exists()) {
            throw new RuntimeException("Document #{$document->id} imported without any chunks.");
        }
        $document->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }
}

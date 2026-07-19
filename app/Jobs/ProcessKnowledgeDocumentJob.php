<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesWithSyncFallback;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, DispatchesWithSyncFallback, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 3600;

    public function __construct(private int $documentId, private bool $embeddingOnly = false)
    {
    }

    public function handle(): void
    {
        $document = KnowledgeDocument::findOrFail($this->documentId);

        if ($document->index_only && ! $this->embeddingOnly) {
            throw new RuntimeException('Index-only documents cannot be reprocessed without their source file.');
        }

        $processingUpdate = [
            'status' => 'processing',
            'processing_error' => null,
        ];
        if ($this->embeddingOnly) {
            $processingUpdate['ingestion_metadata'] = array_merge(
                $document->ingestion_metadata ?? [],
                ['embedding_refresh_in_progress' => true]
            );
        }
        $document->update($processingUpdate);

        try {
            /** @var DocumentTextExtractor $extractor */
            $extractor = app(DocumentTextExtractor::class);
            /** @var TextChunker $chunker */
            $chunker = app(TextChunker::class);
            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            if ($this->embeddingOnly) {
                $this->reembedExistingChunks($document, $llm);
                return;
            }

            Log::info('knowledge.processing.started', [
                'document_id' => $document->id,
                'file' => $document->original_name,
            ]);

            $pages = $extractor->extractFromStoragePath($document->file_path, $document->mime_type);
            $chunks = array_values($chunker->chunk($pages));

            foreach ($chunks as $index => &$chunk) {
                $chunk['chunk_index'] = $chunk['chunk_index'] ?? $index;
                $chunk['page_number'] = $chunk['page_number'] ?? ($pages[0]['page'] ?? null);
                $chunk['end_page_number'] = $chunk['end_page_number'] ?? $chunk['page_number'];
                $chunk['word_count'] = $chunk['word_count'] ?? count(array_filter(
                    preg_split('/\s+/u', trim((string) ($chunk['content'] ?? ''))) ?: []
                ));
            }
            unset($chunk);

            if ($chunks === []) {
                throw new RuntimeException('No readable text could be extracted and chunked from this document.');
            }

            Log::info('knowledge.processing.chunked', [
                'document_id' => $document->id,
                'pages' => count($pages),
                'chunks' => count($chunks),
            ]);

            $dimensions = (int) config('ai.embedding_dimensions', 1536);
            $embeddingModel = (string) config('ai.embedding_model');
            $existing = KnowledgeDocumentChunk::query()
                ->where('knowledge_document_id', $document->id)
                ->get()
                ->keyBy('chunk_index');
            $missing = [];

            foreach ($chunks as $chunk) {
                $stored = $existing->get($chunk['chunk_index']);

                if (
                    $stored !== null &&
                    $stored->content === $chunk['content'] &&
                    (int) $stored->embedding_dimensions === $dimensions &&
                    $this->hasValidEmbedding($stored->embedding, $dimensions) &&
                    ($stored->metadata['embedding_model'] ?? null) === $embeddingModel
                ) {
                    continue;
                }

                $missing[] = $chunk;
            }

            $batchSize = max(1, min(128, (int) config('ai.embedding_batch_size', 16)));

            foreach (array_chunk($missing, $batchSize) as $batchNumber => $batch) {
                $inputs = array_column($batch, 'content');

                Log::info('knowledge.processing.embedding_batch_started', [
                    'document_id' => $document->id,
                    'batch' => $batchNumber + 1,
                    'batch_size' => count($batch),
                    'remaining_chunks' => count($missing) - ($batchNumber * $batchSize),
                ]);

                $embeddings = $llm->embeddingMany($inputs);

                if (count($embeddings) !== count($batch)) {
                    throw new RuntimeException('The embedding provider returned an unexpected number of vectors.');
                }

                foreach ($batch as $offset => $chunk) {
                    $embedding = $embeddings[$offset] ?? [];

                    if (!is_array($embedding) || count($embedding) !== $dimensions) {
                        throw new RuntimeException(sprintf(
                            'Chunk %d received an invalid embedding vector.',
                            $chunk['chunk_index']
                        ));
                    }

                    KnowledgeDocumentChunk::updateOrCreate(
                        [
                            'knowledge_document_id' => $document->id,
                            'chunk_index' => $chunk['chunk_index'],
                        ],
                        [
                            'page_number' => $chunk['page_number'] ?? null,
                            'section_title' => null,
                            'content' => $chunk['content'],
                            'token_count' => $chunk['word_count'] ?? null,
                            'embedding' => json_encode($embedding, JSON_PRESERVE_ZERO_FRACTION),
                            'embedding_dimensions' => count($embedding),
                            'metadata' => [
                                'original_name' => $document->original_name,
                                'source_path' => $document->source_path,
                                'content_hash' => $document->content_hash,
                                'end_page_number' => $chunk['end_page_number'] ?? ($chunk['page_number'] ?? null),
                                'embedding_model' => $embeddingModel,
                            ],
                        ]
                    );
                }

                Log::info('knowledge.processing.embedding_batch_completed', [
                    'document_id' => $document->id,
                    'batch' => $batchNumber + 1,
                    'batch_size' => count($batch),
                ]);
            }

            KnowledgeDocumentChunk::query()
                ->where('knowledge_document_id', $document->id)
                ->where('chunk_index', '>=', count($chunks))
                ->delete();

            $document->update([
                'status' => 'processed',
                'processed_at' => now(),
                'processing_error' => null,
            ]);

            Log::info('knowledge.processing.completed', [
                'document_id' => $document->id,
                'chunks' => count($chunks),
                'embedded_now' => count($missing),
                'resumed_chunks' => count($chunks) - count($missing),
            ]);
        } catch (Throwable $exception) {
            $document->update([
                'status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 60000),
            ]);

            Log::error('knowledge.processing.failed', [
                'document_id' => $document->id,
                'file' => $document->original_name,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        KnowledgeDocument::query()->whereKey($this->documentId)->update([
            'status' => 'failed',
            'processing_error' => mb_substr($exception->getMessage(), 0, 60000),
        ]);
    }

    private function hasValidEmbedding(mixed $value, int $dimensions): bool
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) && count($decoded) === $dimensions;
    }

    private function reembedExistingChunks(
        KnowledgeDocument $document,
        LlmProviderInterface $llm
    ): void {
        $dimensions = (int) config('ai.embedding_dimensions', 1536);
        $embeddingModel = (string) config('ai.embedding_model');
        $batchSize = max(1, min(128, (int) config('ai.embedding_batch_size', 16)));
        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        if ($chunks->isEmpty()) {
            throw new RuntimeException('No existing chunks are available for embedding-only refresh.');
        }

        $pending = $chunks->filter(function (KnowledgeDocumentChunk $chunk) use ($dimensions, $embeddingModel): bool {
            return (int) $chunk->embedding_dimensions !== $dimensions
                || ! $this->hasValidEmbedding($chunk->embedding, $dimensions)
                || ($chunk->metadata['embedding_model'] ?? null) !== $embeddingModel;
        })->values()->all();

        Log::info('knowledge.reembedding.started', [
            'document_id' => $document->id,
            'model' => $embeddingModel,
            'dimensions' => $dimensions,
            'total_chunks' => $chunks->count(),
            'pending_chunks' => count($pending),
        ]);

        foreach (array_chunk($pending, $batchSize) as $batchNumber => $batch) {
            $embeddings = $llm->embeddingMany(array_map(
                fn (KnowledgeDocumentChunk $chunk): string => $chunk->content,
                $batch
            ));

            if (count($embeddings) !== count($batch)) {
                throw new RuntimeException('The embedding provider returned an unexpected number of vectors.');
            }

            foreach ($batch as $offset => $chunk) {
                $embedding = $embeddings[$offset] ?? [];

                if (! is_array($embedding) || count($embedding) !== $dimensions) {
                    throw new RuntimeException("Chunk #{$chunk->id} received an invalid embedding vector.");
                }

                $chunk->update([
                    'embedding' => json_encode($embedding, JSON_PRESERVE_ZERO_FRACTION),
                    'embedding_dimensions' => $dimensions,
                    'metadata' => array_merge($chunk->metadata ?? [], [
                        'embedding_model' => $embeddingModel,
                    ]),
                ]);
            }

            Log::info('knowledge.reembedding.batch_completed', [
                'document_id' => $document->id,
                'batch' => $batchNumber + 1,
                'batch_size' => count($batch),
            ]);
        }

        $document->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processing_error' => null,
            'ingestion_metadata' => array_merge($document->ingestion_metadata ?? [], [
                'embedding_refresh_in_progress' => false,
                'embedding_model' => $embeddingModel,
                'embedding_dimensions' => $dimensions,
            ]),
        ]);

        Log::info('knowledge.reembedding.completed', [
            'document_id' => $document->id,
            'embedded_now' => count($pending),
            'resumed_chunks' => $chunks->count() - count($pending),
        ]);
    }
}

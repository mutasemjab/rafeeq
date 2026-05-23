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
use Throwable;

class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, DispatchesWithSyncFallback, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $documentId)
    {
    }

    public function handle(): void
    {
        $doc = KnowledgeDocument::findOrFail($this->documentId);

        $doc->update([
            'status'           => 'processing',
            'processing_error' => null,
        ]);

        try {
            /** @var DocumentTextExtractor $extractor */
            $extractor = app(DocumentTextExtractor::class);

            /** @var TextChunker $chunker */
            $chunker = app(TextChunker::class);

            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            // Extract text from the document file.
            $pages = $extractor->extractFromStoragePath($doc->file_path, $doc->mime_type);

            // Merge all page text into a single string for chunking.
            $fullText = collect($pages)->pluck('text')->implode(' ');

            // Chunk the full text.
            $chunks = $chunker->chunk($fullText);

            // Delete any previous chunks for this document.
            KnowledgeDocumentChunk::where('knowledge_document_id', $doc->id)->delete();

            // Persist new chunks with embeddings.
            foreach ($chunks as $i => $chunk) {
                KnowledgeDocumentChunk::create([
                    'knowledge_document_id' => $doc->id,
                    'chunk_index'           => $i,
                    'content'               => $chunk['content'],
                    'token_count'           => str_word_count($chunk['content']),
                    'embedding'             => json_encode($llm->embedding($chunk['content'])),
                    'embedding_dimensions'  => (int) config('ai.embedding_dimensions'),
                    'metadata'              => [
                        'original_name' => $doc->original_name,
                        'page_hint'     => $pages[0]['page'] ?? null,
                    ],
                ]);
            }

            $doc->update([
                'status'           => 'processed',
                'processed_at'     => now(),
                'processing_error' => null,
            ]);
        } catch (Throwable $e) {
            $doc->update([
                'status'           => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
        }
    }
}

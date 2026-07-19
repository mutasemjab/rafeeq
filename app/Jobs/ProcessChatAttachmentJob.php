<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesWithSyncFallback;
use App\Models\ChatAttachment;
use App\Models\ChatAttachmentChunk;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessChatAttachmentJob implements ShouldQueue
{
    use Dispatchable, DispatchesWithSyncFallback, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $attachmentId)
    {
    }

    public function handle(): void
    {
        $att = ChatAttachment::findOrFail($this->attachmentId);

        $att->update(['status' => 'processing']);

        try {
            /** @var DocumentTextExtractor $extractor */
            $extractor = app(DocumentTextExtractor::class);

            /** @var TextChunker $chunker */
            $chunker = app(TextChunker::class);

            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            // Extract text from the attachment file.
            $pages = $extractor->extractFromStoragePath($att->file_path, $att->mime_type);

            // Merge all page text into a single string for chunking.
            $fullText = collect($pages)->pluck('text')->implode(' ');

            // Chunk the full text.
            $chunks = $chunker->chunk($fullText);

            if ($chunks === []) {
                throw new RuntimeException('No readable text could be extracted from this attachment.');
            }

            $dimensions = (int) config('ai.embedding_dimensions', 1536);
            $batchSize = max(1, min(128, (int) config('ai.embedding_batch_size', 16)));
            $embeddings = [];

            foreach (array_chunk($chunks, $batchSize) as $batch) {
                $vectors = $llm->embeddingMany(array_column($batch, 'content'));
                if (count($vectors) !== count($batch)) {
                    throw new RuntimeException('The embedding provider returned an unexpected number of vectors.');
                }
                foreach ($vectors as $vector) {
                    if (! is_array($vector) || count($vector) !== $dimensions) {
                        throw new RuntimeException('The embedding provider returned an invalid vector.');
                    }
                    $embeddings[] = $vector;
                }
            }

            DB::transaction(function () use ($att, $chunks, $embeddings, $dimensions): void {
                ChatAttachmentChunk::where('chat_attachment_id', $att->id)->delete();

                foreach ($chunks as $i => $chunk) {
                    ChatAttachmentChunk::create([
                        'chat_attachment_id'   => $att->id,
                        'conversation_id'      => $att->conversation_id,
                        'user_id'              => $att->user_id,
                        'child_id'             => $att->child_id,
                        'chunk_index'          => $i,
                        'content'              => $chunk['content'],
                        'token_count'          => str_word_count($chunk['content']),
                        'embedding'            => json_encode($embeddings[$i], JSON_PRESERVE_ZERO_FRACTION),
                        'embedding_dimensions' => $dimensions,
                        'metadata'             => [
                            'original_name' => $att->original_name,
                            'embedding_model' => config('ai.embedding_model'),
                        ],
                    ]);
                }
            });

            $att->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $att->update([
                'status'           => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
        }
    }
}

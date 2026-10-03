<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesWithSyncFallback;
use App\Models\ChatAttachment;
use App\Models\ChatAttachmentChunk;
use App\Models\User;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\PrivateDocumentStorage;
use App\Services\Documents\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $att = ChatAttachment::find($this->attachmentId);
        if (! $att || ! $this->canProcess($att)) {
            return;
        }

        if (! ChatAttachment::whereKey($att->id)->where('status', '!=', 'processing')->update(['status' => 'processing', 'processing_error' => null])) {
            return;
        }

        try {
            /** @var DocumentTextExtractor $extractor */
            $extractor = app(DocumentTextExtractor::class);

            /** @var TextChunker $chunker */
            $chunker = app(TextChunker::class);

            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            // Extract text from the attachment file.
            $pages = $extractor->extractFromAbsolutePath(app(PrivateDocumentStorage::class)->absolutePath($att), $att->mime_type, false);

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
                if (! ChatAttachment::whereKey($att->id)->exists() || ! $this->canProcess($att)) {
                    ChatAttachment::whereKey($att->id)->update(['status' => 'uploaded']);

                    return;
                }
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
                $att = ChatAttachment::query()->lockForUpdate()->find($att->id);
                if (! $att) {
                    return;
                }
                if (! $this->canProcess($att)) {
                    $att->update(['status' => 'uploaded', 'processed_at' => null]);

                    return;
                }
                ChatAttachmentChunk::where('chat_attachment_id', $att->id)->delete();

                foreach ($chunks as $i => $chunk) {
                    ChatAttachmentChunk::create([
                        'chat_attachment_id' => $att->id,
                        'conversation_id' => $att->conversation_id,
                        'user_id' => $att->user_id,
                        'child_id' => $att->child_id,
                        'chunk_index' => $i,
                        'content' => $chunk['content'],
                        'token_count' => str_word_count($chunk['content']),
                        'embedding' => json_encode($embeddings[$i], JSON_PRESERVE_ZERO_FRACTION),
                        'embedding_dimensions' => $dimensions,
                        'metadata' => [
                            'original_name' => $att->original_name,
                            'embedding_model' => config('ai.embedding_model'),
                        ],
                    ]);
                }
                $att->update(['status' => 'processed', 'processing_error' => null, 'processed_at' => now()]);
            });
        } catch (Throwable $e) {
            $this->failed($e);
        }
    }

    private function canProcess(ChatAttachment $attachment): bool
    {
        return User::whereKey($attachment->user_id)->whereNotNull('ai_consent_accepted_at')->exists()
            && $attachment->conversation()->where('user_id', $attachment->user_id)->exists()
            && ($attachment->child_id === null || $attachment->child()->where('user_id', $attachment->user_id)->exists());
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('private_attachment.processing_failed', ['attachment_id' => $this->attachmentId, 'exception' => $exception::class]);
        ChatAttachment::whereKey($this->attachmentId)->update([
            'status' => 'failed', 'processing_error' => 'Unable to process this file. Retry or upload a clearer supported file.', 'processed_at' => null,
        ]);
    }
}

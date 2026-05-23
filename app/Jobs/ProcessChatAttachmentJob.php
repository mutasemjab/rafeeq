<?php

namespace App\Jobs;

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
use Throwable;

class ProcessChatAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

            // Delete any previous chunks for this attachment.
            ChatAttachmentChunk::where('chat_attachment_id', $att->id)->delete();

            // Persist new chunks with embeddings.
            foreach ($chunks as $i => $chunk) {
                ChatAttachmentChunk::create([
                    'chat_attachment_id'   => $att->id,
                    'conversation_id'      => $att->conversation_id,
                    'user_id'              => $att->user_id,
                    'child_id'             => $att->child_id,
                    'chunk_index'          => $i,
                    'content'              => $chunk['content'],
                    'token_count'          => str_word_count($chunk['content']),
                    'embedding'            => json_encode($llm->embedding($chunk['content'])),
                    'embedding_dimensions' => (int) config('ai.embedding_dimensions'),
                    'metadata'             => [
                        'original_name' => $att->original_name,
                    ],
                ]);
            }

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

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessChatAttachmentJob;
use App\Models\ChatAttachment;
use Illuminate\Console\Command;

class ReembedChatAttachmentsCommand extends Command
{
    protected $signature = 'chat-attachments:reembed
        {--queue : Dispatch refresh jobs instead of processing inline}
        {--force : Refresh every processed attachment}';

    protected $description = 'Re-embed existing chat attachments for the configured embedding model';

    public function handle(): int
    {
        $expectedModel = (string) config('ai.embedding_model');
        $expectedDimensions = (int) config('ai.embedding_dimensions', 1536);
        $queued = 0;
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $attachments = ChatAttachment::query()
            ->where('status', 'processed')
            ->whereHas('chunks')
            ->orderBy('id')
            ->cursor();

        foreach ($attachments as $attachment) {
            $needsRefresh = (bool) $this->option('force');

            if (! $needsRefresh) {
                foreach ($attachment->chunks()
                    ->select(['id', 'embedding', 'embedding_dimensions', 'metadata'])
                    ->cursor() as $chunk) {
                    $embedding = json_decode((string) $chunk->embedding, true);
                    if (
                        ! is_array($embedding)
                        || count($embedding) !== $expectedDimensions
                        || (int) $chunk->embedding_dimensions !== $expectedDimensions
                        || ($chunk->metadata['embedding_model'] ?? null) !== $expectedModel
                    ) {
                        $needsRefresh = true;
                        break;
                    }
                }
            }

            if (! $needsRefresh) {
                $skipped++;
                continue;
            }

            if ($this->option('queue')) {
                ProcessChatAttachmentJob::dispatch($attachment->id);
                $queued++;
                continue;
            }

            ProcessChatAttachmentJob::dispatchSync($attachment->id);
            $attachment->refresh();
            if ($attachment->status === 'processed') {
                $processed++;
            } else {
                $failed++;
                $this->error("Attachment #{$attachment->id} failed: {$attachment->processing_error}");
            }
        }

        $this->info(sprintf(
            'Chat attachment refresh complete: %d processed, %d queued, %d current, %d failed.',
            $processed,
            $queued,
            $skipped,
            $failed
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

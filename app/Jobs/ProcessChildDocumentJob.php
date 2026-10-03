<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesWithSyncFallback;
use App\Models\ChildDocument;
use App\Models\User;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessChildDocumentJob implements ShouldQueue
{
    use Dispatchable, DispatchesWithSyncFallback, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_STORED_CHARS = 24000;

    public function __construct(public int $documentId)
    {
    }

    public function handle(DocumentTextExtractor $extractor): void
    {
        $document = ChildDocument::query()->find($this->documentId);
        if (! $document || ! $document->child()->where('user_id', $document->user_id)->exists()) {
            return;
        }
        // Consent can be withdrawn between upload and worker execution. OCR
        // may use an AI provider, so re-check it at execution time as well.
        if (! User::query()->whereKey($document->user_id)->whereNotNull('ai_consent_accepted_at')->exists()) {
            return;
        }
        if (! ChildDocument::whereKey($document->id)->where('status', '!=', 'processing')->update(['status' => 'processing', 'processing_error' => null])) {
            return;
        }

        try {
            $realPath = app(PrivateDocumentStorage::class)->absolutePath($document);
            $pages = $extractor->extractFromAbsolutePath($realPath, $document->mime_type, false);
            $text = trim(collect($pages)->pluck('text')->filter(fn ($text) => is_string($text))->implode("\n\n"));
            if ($text === '') {
                throw new RuntimeException('No readable text could be extracted from this document.');
            }

            $length = mb_strlen($text);
            if ($length > self::MAX_STORED_CHARS) {
                // Keep the conclusion, which often contains the assessment,
                // as well as the history at the beginning of a long report.
                $marker = "\n[... omitted middle of document ...]\n";
                $half = (int) floor((self::MAX_STORED_CHARS - mb_strlen($marker)) / 2);
                $text = mb_substr($text, 0, $half).$marker.mb_substr($text, -$half);
            }
            DB::transaction(function () use ($document, $text, $length): void {
                $document = ChildDocument::query()->lockForUpdate()->find($document->id);
                if (! $document) {
                    return;
                }
                if (! User::whereKey($document->user_id)->whereNotNull('ai_consent_accepted_at')->exists()
                    || ! $document->child()->where('user_id', $document->user_id)->exists()) {
                    $document->update(['status' => 'uploaded', 'processed_at' => null]);

                    return;
                }
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $document->update([
                    'status' => 'processed',
                    'processing_error' => null,
                    'processed_at' => now(),
                    'metadata' => array_merge($metadata, [
                        'extracted_text' => $text,
                        'extracted_text_truncated' => $length > self::MAX_STORED_CHARS,
                        'extracted_text_length' => $length,
                        'extraction_version' => 1,
                    ]),
                ]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('private_document.processing_failed', ['document_id' => $this->documentId, 'exception' => $exception::class]);
        ChildDocument::whereKey($this->documentId)->update([
            'status' => 'failed', 'processing_error' => 'Unable to read this document. Upload a clearer supported file.', 'processed_at' => null,
        ]);
    }
}

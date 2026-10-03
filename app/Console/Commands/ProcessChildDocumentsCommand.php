<?php

namespace App\Console\Commands;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\ChildDocument;
use App\Models\User;
use Illuminate\Console\Command;

class ProcessChildDocumentsCommand extends Command
{
    protected $signature = 'child-documents:process {--id= : Process one document} {--retry-failed : Also retry failed documents}';

    protected $description = 'Queue text extraction for existing child documents without readable text';

    public function handle(): int
    {
        $query = ChildDocument::query()
            ->whereIn('user_id', User::query()->select('id')->whereNotNull('ai_consent_accepted_at'))
            ->when($this->option('id'), fn ($query, $id) => $query->whereKey($id))
            ->whereIn('status', $this->option('retry-failed') ? ['uploaded', 'processed', 'failed'] : ['uploaded', 'processed']);
        $queued = 0;
        $query->chunkById(100, function ($documents) use (&$queued): void {
            foreach ($documents as $document) {
                if ($document->status === 'processed' && trim((string) data_get($document->metadata, 'extracted_text', '')) !== '') {
                    continue;
                }
                ProcessChildDocumentJob::dispatchWithSyncFallback((int) $document->id);
                $queued++;
            }
        });
        $this->info("Queued {$queued} child document(s) for text extraction.");

        return self::SUCCESS;
    }
}

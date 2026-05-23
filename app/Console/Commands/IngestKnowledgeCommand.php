<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IngestKnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:ingest
                            {path=knowledge : Storage path to scan for documents}
                            {--category= : Optional category tag applied to every ingested document}
                            {--sync : Process documents synchronously instead of dispatching to the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest system knowledge files into the database and queue them for processing.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storagePath = $this->argument('path');
        $category    = $this->option('category') ?: null;
        $sync        = (bool) $this->option('sync');

        $this->info("Scanning storage path: {$storagePath}");

        $files     = Storage::files($storagePath);
        $supported = ['pdf', 'docx', 'doc', 'pptx', 'txt'];

        if (empty($files)) {
            $this->warn('No files found in the specified path.');
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (!in_array($ext, $supported, true)) {
                $this->warn("Skipping unsupported file type: {$file}");
                continue;
            }

            $name = basename($file);

            // Skip files that have already been processed successfully.
            $existing = KnowledgeDocument::where('original_name', $name)
                ->where('file_path', $file)
                ->first();

            if ($existing && $existing->status === 'processed') {
                $this->line("Already processed: {$name}");
                continue;
            }

            // Determine mime type for the stored file.
            $absolutePath = Storage::path($file);
            $mimeType     = file_exists($absolutePath)
                ? (mime_content_type($absolutePath) ?: null)
                : null;

            // Upsert the KnowledgeDocument record.
            $doc = KnowledgeDocument::updateOrCreate(
                ['file_path' => $file],
                [
                    'title'         => KnowledgeDocument::titleFromFilename($name),
                    'original_name' => $name,
                    'mime_type'     => $mimeType,
                    'file_size'     => file_exists($absolutePath) ? filesize($absolutePath) : null,
                    'category'      => $category,
                    'status'        => 'uploaded',
                    'uploaded_by'   => null,
                ]
            );

            if ($sync) {
                ProcessKnowledgeDocumentJob::dispatchSync($doc->id);
            } else {
                ProcessKnowledgeDocumentJob::dispatch($doc->id);
            }

            $this->info("Queued: {$name}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

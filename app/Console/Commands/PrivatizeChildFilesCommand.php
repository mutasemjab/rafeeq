<?php

namespace App\Console\Commands;

use App\Models\ChatAttachment;
use App\Models\ChildDocument;
use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrivatizeChildFilesCommand extends Command
{
    protected $signature = 'private-files:backfill {--dry-run : List counts without copying or changing records}';

    protected $description = 'Copy legacy child files into private storage and verify integrity without deleting originals';

    public function handle(PrivateDocumentStorage $storage): int
    {
        $copied = 0;
        $failed = 0;
        foreach ([ChatAttachment::class, ChildDocument::class] as $model) {
            $model::query()->where('storage_disk', 'public')->chunkById(100, function ($documents) use ($storage, &$copied, &$failed): void {
                foreach ($documents as $document) {
                    try {
                        if ($this->option('dry-run')) {
                            $storage->absolutePath($document);
                            $copied++;

                            continue;
                        }
                        $didCopy = DB::transaction(function () use ($document, $storage): bool {
                            // Match the deletion lock so a concurrent delete cannot
                            // leave a new, untracked private copy after it completes.
                            $document = $document::query()->lockForUpdate()->find($document->id);
                            if (! $document || $document->storage_disk !== 'public') {
                                return false;
                            }
                            $source = $storage->absolutePath($document);
                            $alreadyCopied = Storage::disk('private')->exists($document->file_path);
                            try {
                                if (! $alreadyCopied) {
                                    $stream = fopen($source, 'rb');
                                    try {
                                        if (! is_resource($stream) || ! Storage::disk('private')->put($document->file_path, $stream)) {
                                            throw new RuntimeException('Private copy failed.');
                                        }
                                    } finally {
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    }
                                }
                                $copyRecord = clone $document;
                                $copyRecord->storage_disk = 'private';
                                $copy = $storage->absolutePath($copyRecord);
                                if (! hash_equals(hash_file('sha256', $source), hash_file('sha256', $copy))) {
                                    throw new RuntimeException('Private copy integrity check failed.');
                                }
                            } catch (Throwable $exception) {
                                // Clean up only a new partial copy from this attempt;
                                // never overwrite/delete a pre-existing private file.
                                if (! $alreadyCopied) {
                                    Storage::disk('private')->delete($document->file_path);
                                }
                                throw $exception;
                            }
                            $document->update(['storage_disk' => 'private', 'has_legacy_public_copy' => true]);

                            return true;
                        });
                        $copied += (int) $didCopy;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error(class_basename($document).' #'.$document->id.': copy unavailable; original retained.');
                    }
                }
            });
        }
        $this->info(($this->option('dry-run') ? 'Eligible' : 'Copied and verified').": {$copied}; failed: {$failed}.");
        $this->warn('Legacy originals were retained. Deny direct web access to storage/chat-attachments and storage/children/*/documents before release; see private-file deployment documentation.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

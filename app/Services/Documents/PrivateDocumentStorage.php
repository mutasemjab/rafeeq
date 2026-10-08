<?php

namespace App\Services\Documents;

use App\Models\ChatAttachment;
use App\Models\ChildDocument;
use App\Models\PersonDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateDocumentStorage
{
    public function absolutePath(ChatAttachment|ChildDocument|PersonDocument $document): string
    {
        $disk = $document->storage_disk ?: 'public';
        if (! in_array($disk, ['private', 'public'], true)) {
            throw new RuntimeException('Unsupported document storage.');
        }

        return $this->resolve($disk, (string) $document->file_path);
    }

    private function resolve(string $disk, string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $path)) {
            throw new RuntimeException('The document is not available in upload storage.');
        }
        $root = realpath(Storage::disk($disk)->path(''));
        $realPath = realpath(Storage::disk($disk)->path($path));
        if ($root === false || $realPath === false || ! is_file($realPath) || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The document is not available in upload storage.');
        }

        return $realPath;
    }

    public function download(ChatAttachment|ChildDocument|PersonDocument $document): BinaryFileResponse
    {
        try {
            $path = $this->absolutePath($document);
        } catch (RuntimeException $exception) {
            abort(404, 'File not available.');
        }
        $filename = basename(str_replace('\\', '/', (string) $document->original_name));

        return response()->download($path, $filename ?: 'document', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function temporaryUrl(ChatAttachment|ChildDocument|PersonDocument $document): ?string
    {
        if (! $document->file_path) {
            return null;
        }
        $attachment = $document instanceof ChatAttachment;

        return URL::temporarySignedRoute(
            $attachment ? 'attachments.temporary-download' : ($document instanceof PersonDocument ? 'person-documents.temporary-download' : 'child-documents.temporary-download'),
            now()->addMinutes(5),
            [$attachment ? 'attachment' : 'document' => $document->id, 'owner' => $document->user_id]
        );
    }

    public function delete(ChatAttachment|ChildDocument|PersonDocument $document): void
    {
        $disks = [$document->storage_disk ?: 'public'];
        if ($document->has_legacy_public_copy) {
            $disks[] = 'public';
        }
        foreach (array_unique($disks) as $disk) {
            // Resolve before deleting so tampered database paths cannot delete
            // arbitrary local files. A missing file is already deleted.
            if (! in_array($disk, ['private', 'public'], true)) {
                throw new RuntimeException('Unsupported document storage.');
            }
            try {
                $absolutePath = $this->resolve($disk, $document->file_path);
            } catch (RuntimeException $exception) {
                if (! Storage::disk($disk)->exists($document->file_path)) {
                    continue;
                }
                throw $exception;
            }
            $this->deleteLegacyExtractionCache($absolutePath);
            if (! Storage::disk($disk)->delete($document->file_path)) {
                throw new RuntimeException('The file could not be deleted.');
            }
        }
    }

    private function deleteLegacyExtractionCache(string $absolutePath): void
    {
        // Earlier versions cached private extraction by content hash in the
        // shared cache. Remove only this exact derived cache on file deletion.
        // Missing originals cannot be matched and need a separate retention audit.
        $root = realpath(storage_path('app/knowledge-extraction-cache'));
        if ($root === false) {
            return;
        }
        $hash = @hash_file('sha256', $absolutePath);
        if (! is_string($hash)) {
            throw new RuntimeException('The file cache could not be checked.');
        }
        $cachePath = $root.'/'.substr($hash, 0, 2).'/'.$hash.'.json';
        if (! is_file($cachePath)) {
            return;
        }
        $realPath = realpath($cachePath);
        if ($realPath === false || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR) || ! @unlink($realPath)) {
            throw new RuntimeException('The file cache could not be deleted.');
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use App\Services\AI\OpenAiConfigResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class IngestKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:ingest
        {path=knowledge : Storage path, absolute file path, or local directory path}
        {--category=general : Category applied to imported documents}
        {--disk=local : Storage disk when path is not a real filesystem path}
        {--process : Extract, chunk, and embed inline in this CLI process}
        {--sync : Alias for --process}
        {--queue : Dispatch processing jobs to the configured queue}
        {--register-only : Register source files without processing them}
        {--force : Re-extract and re-embed documents already processed}
        {--reembed : Refresh only embeddings whose model or dimensions are outdated}
        {--dry-run : Analyze files without touching the database or storage}
        {--preflight : Validate API, extensions, tools, database, and storage}
        {--link : Hard-link local files into storage when possible, then fall back to copy}
        {--limit= : Maximum number of unique supported files to handle}
        {--report= : Write a detailed JSON source-analysis report}';

    protected $description = 'Recursively analyze, deduplicate, import, and embed knowledge-base files';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $disk = (string) $this->option('disk');
        $category = trim((string) $this->option('category')) ?: 'general';
        $shouldProcess = (bool) ($this->option('process') || $this->option('sync'));
        $shouldQueue = (bool) $this->option('queue');
        $registerOnly = (bool) $this->option('register-only');
        $dryRun = (bool) $this->option('dry-run');
        $preflightOnly = (bool) $this->option('preflight');
        $force = (bool) $this->option('force');
        $reembed = (bool) $this->option('reembed');

        if ($shouldProcess && $shouldQueue) {
            $this->error('Use either --process/--sync or --queue, not both.');
            return self::FAILURE;
        }

        if ($registerOnly && ($shouldProcess || $shouldQueue)) {
            $this->error('Use --register-only without --process, --sync, or --queue.');
            return self::FAILURE;
        }

        try {
            $limit = $this->resolveLimit();
            $this->info("Analyzing source: {$path}");
            $analysis = $this->analyzeSource($path, $disk, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->renderAnalysis($analysis);

        if ($this->option('report')) {
            try {
                $reportPath = $this->writeReport((string) $this->option('report'), $analysis);
                $this->info("Analysis report written: {$reportPath}");
            } catch (Throwable $exception) {
                $this->error('Unable to write analysis report: '.$exception->getMessage());
                return self::FAILURE;
            }
        }

        $preflight = $this->preflight($analysis, $shouldProcess || $preflightOnly);
        $this->renderPreflight($preflight);

        if ($preflightOnly) {
            return $preflight['ready'] ? self::SUCCESS : self::FAILURE;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (!$preflight['ready']) {
            $this->error('Preflight failed. Resolve the required items above before ingestion.');
            return self::FAILURE;
        }

        if ($analysis['files'] === []) {
            $this->error('No unique supported knowledge files were found.');
            return self::FAILURE;
        }

        $stats = [
            'registered' => 0,
            'existing' => 0,
            'processed' => 0,
            'queued' => 0,
            'failed' => 0,
        ];

        foreach ($analysis['files'] as $index => $file) {
            $this->line(sprintf(
                '[%d/%d] %s',
                $index + 1,
                count($analysis['files']),
                $file['relative_path']
            ));

            try {
                [$document, $wasExisting] = $this->importFile(
                    $file,
                    $category,
                    $force,
                    (bool) $this->option('link')
                );

                $stats['registered']++;
                $stats[$wasExisting ? 'existing' : 'registered'] += $wasExisting ? 1 : 0;

                $embeddingRefresh = $reembed
                    && (
                        $document->status === 'processed'
                        || (bool) ($document->ingestion_metadata['embedding_refresh_in_progress'] ?? false)
                    )
                    && $this->needsEmbeddingRefresh($document);

                if ($wasExisting && !$force && $document->status === 'processed' && !$embeddingRefresh) {
                    $this->line("  Already processed as document #{$document->id}");
                    continue;
                }

                $embeddingOnly = $embeddingRefresh && $document->chunks()->exists();

                if ($registerOnly) {
                    $this->line("  Registered as document #{$document->id}");
                    continue;
                }

                if ($shouldProcess) {
                    ProcessKnowledgeDocumentJob::dispatchSync($document->id, $embeddingOnly);
                    $document->refresh();

                    if ($document->status === 'processed') {
                        $stats['processed']++;
                        $this->info("  Processed document #{$document->id} ({$document->chunks()->count()} chunks)");
                    } else {
                        $stats['failed']++;
                        $this->error('  Failed: '.($document->processing_error ?: 'unknown processing error'));
                    }

                    continue;
                }

                ProcessKnowledgeDocumentJob::dispatch($document->id, $embeddingOnly);
                $stats['queued']++;
                $this->line("  Queued document #{$document->id}");
            } catch (Throwable $exception) {
                $stats['failed']++;
                $this->error('  Failed: '.$exception->getMessage());
                Log::error('knowledge.ingest.file_failed', [
                    'source' => $file['absolute_path'],
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Finished: %d unique files registered, %d existing, %d processed, %d queued, %d failed.',
            $stats['registered'],
            $stats['existing'],
            $stats['processed'],
            $stats['queued'],
            $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveLimit(): ?int
    {
        $value = $this->option('limit');
        if ($value === null || $value === '') {
            return null;
        }
        $limit = (int) $value;
        if ($limit < 1) {
            throw new RuntimeException('--limit must be a positive integer.');
        }
        return $limit;
    }

    private function needsEmbeddingRefresh(KnowledgeDocument $document): bool
    {
        $expectedModel = (string) config('ai.embedding_model');
        $expectedDimensions = (int) config('ai.embedding_dimensions', 1536);
        $hasChunks = false;

        foreach ($document->chunks()
            ->select(['id', 'embedding', 'embedding_dimensions', 'metadata'])
            ->cursor() as $chunk) {
            $hasChunks = true;
            $embedding = is_string($chunk->embedding)
                ? json_decode($chunk->embedding, true)
                : $chunk->embedding;

            if (
                (int) $chunk->embedding_dimensions !== $expectedDimensions
                || ! is_array($embedding)
                || count($embedding) !== $expectedDimensions
                || ($chunk->metadata['embedding_model'] ?? null) !== $expectedModel
            ) {
                return true;
            }
        }

        return ! $hasChunks;
    }

    private function analyzeSource(string $path, string $disk, ?int $limit): array
    {
        [$rawFiles, $sourceRoot] = $this->discoverFiles($path, $disk);
        $supported = array_flip(KnowledgeDocument::supportedUploadExtensions());
        $files = [];
        $skipped = [];
        $seenHashes = [];
        $byExtension = [];
        $uniqueByExtension = [];
        $totalBytes = 0;
        $uniqueBytes = 0;

        foreach ($rawFiles as $raw) {
            $name = $raw['original_name'];
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $byExtension[$extension ?: '[none]'] = ($byExtension[$extension ?: '[none]'] ?? 0) + 1;

            if ($this->isMetadataOrTemporary($name)) {
                $raw['skip_reason'] = 'metadata_or_temporary';
                $skipped[] = $raw;
                continue;
            }

            if (!isset($supported[$extension])) {
                $raw['skip_reason'] = 'unsupported_extension';
                $skipped[] = $raw;
                continue;
            }

            if (!is_readable($raw['absolute_path'])) {
                $raw['skip_reason'] = 'unreadable';
                $skipped[] = $raw;
                continue;
            }

            $size = filesize($raw['absolute_path']) ?: 0;

            if ($size === 0) {
                $raw['skip_reason'] = 'empty_file';
                $skipped[] = $raw;
                continue;
            }

            $totalBytes += $size;
            $hash = hash_file('sha256', $raw['absolute_path']);

            if (!is_string($hash) || strlen($hash) !== 64) {
                $raw['skip_reason'] = 'hash_failed';
                $skipped[] = $raw;
                continue;
            }

            if (isset($seenHashes[$hash])) {
                $raw['skip_reason'] = 'duplicate_content';
                $raw['duplicate_of'] = $seenHashes[$hash];
                $raw['content_hash'] = $hash;
                $skipped[] = $raw;
                continue;
            }

            $relativePath = $raw['source'] === 'filesystem'
                ? $this->relativePath($sourceRoot, $raw['absolute_path'])
                : $raw['path'];
            $raw += [
                'extension' => $extension,
                'mime_type' => mime_content_type($raw['absolute_path']) ?: 'application/octet-stream',
                'file_size' => $size,
                'modified_at' => filemtime($raw['absolute_path']) ?: null,
                'content_hash' => $hash,
                'relative_path' => $relativePath,
            ];
            $files[] = $raw;
            $seenHashes[$hash] = $relativePath;
            $uniqueBytes += $size;
            $uniqueByExtension[$extension] = ($uniqueByExtension[$extension] ?? 0) + 1;

            if ($limit !== null && count($files) >= $limit) {
                break;
            }
        }

        ksort($byExtension);
        ksort($uniqueByExtension);
        $skipReasons = [];
        foreach ($skipped as $item) {
            $reason = $item['skip_reason'];
            $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => $path,
            'source_root' => $sourceRoot,
            'summary' => [
                'scanned_files' => count($rawFiles),
                'unique_supported_files' => count($files),
                'skipped_files' => count($skipped),
                'duplicate_files' => $skipReasons['duplicate_content'] ?? 0,
                'metadata_or_temporary_files' => $skipReasons['metadata_or_temporary'] ?? 0,
                'unsupported_files' => $skipReasons['unsupported_extension'] ?? 0,
                'empty_files' => $skipReasons['empty_file'] ?? 0,
                'candidate_bytes' => $totalBytes,
                'unique_bytes' => $uniqueBytes,
                'by_extension' => $byExtension,
                'unique_by_extension' => $uniqueByExtension,
                'skip_reasons' => $skipReasons,
            ],
            'files' => $files,
            'skipped' => $skipped,
        ];
    }

    private function discoverFiles(string $path, string $disk): array
    {
        $realPath = realpath($path);

        if ($realPath !== false) {
            if (is_file($realPath)) {
                return [[[
                    'source' => 'filesystem',
                    'path' => $realPath,
                    'absolute_path' => $realPath,
                    'display_path' => $realPath,
                    'original_name' => basename($realPath),
                ]], dirname($realPath)];
            }

            if (!is_dir($realPath)) {
                throw new RuntimeException("Path is not a file or directory: {$path}");
            }

            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $files[] = [
                    'source' => 'filesystem',
                    'path' => $file->getPathname(),
                    'absolute_path' => $file->getPathname(),
                    'display_path' => $file->getPathname(),
                    'original_name' => $file->getBasename(),
                ];
            }

            usort($files, fn(array $left, array $right): int =>
                strcmp($left['absolute_path'], $right['absolute_path'])
            );
            return [$files, $realPath];
        }

        $storage = Storage::disk($disk);
        if (!$storage->exists($path)) {
            throw new RuntimeException("Path not found as a filesystem or {$disk} storage path: {$path}");
        }

        $paths = is_file($storage->path($path)) ? [$path] : $storage->allFiles($path);
        $files = [];
        foreach ($paths as $filePath) {
            $files[] = [
                'source' => 'storage',
                'disk' => $disk,
                'path' => $filePath,
                'absolute_path' => $storage->path($filePath),
                'display_path' => "{$disk}:{$filePath}",
                'original_name' => basename($filePath),
            ];
        }
        return [$files, $path];
    }

    private function isMetadataOrTemporary(string $name): bool
    {
        return $name === '.DS_Store'
            || strcasecmp($name, 'Thumbs.db') === 0
            || strcasecmp($name, 'desktop.ini') === 0
            || str_starts_with($name, '._')
            || str_starts_with($name, '~$');
    }

    private function relativePath(string $root, string $path): string
    {
        if (is_file($root)) {
            return basename($path);
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : basename($path);
    }

    private function preflight(array $analysis, bool $checkRuntime): array
    {
        $checks = [];
        $ready = true;
        $extensions = array_keys($analysis['summary']['unique_by_extension']);
        $finder = new ExecutableFinder();
        $has = fn(string $command): bool => $finder->find($command) !== null;

        $required = [];
        if (in_array('pdf', $extensions, true) && !$has('pdftotext') && !$has('gs')) {
            $required[] = 'Install Poppler (pdftotext) or Ghostscript for PDF extraction.';
        }
        if (array_intersect($extensions, ['ppt', 'xls']) !== [] && !$has('soffice')) {
            $required[] = 'Install LibreOffice (soffice) for legacy PPT/XLS conversion.';
        }
        if (in_array('doc', $extensions, true) && !$has('soffice') && !$has('textutil') && !$has('antiword') && !$has('catdoc')) {
            $required[] = 'Install LibreOffice, textutil, antiword, or catdoc for legacy DOC extraction.';
        }
        if (array_intersect($extensions, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff']) !== [] && !$has('tesseract')) {
            $required[] = 'Install Tesseract with ara and eng language data for image OCR.';
        }
        if (array_intersect($extensions, ['mp3', 'm4a', 'wav', 'mp4', 'mov', 'm4v', 'avi', 'flv']) !== [] && !$has('ffmpeg')) {
            $required[] = 'Install ffmpeg for audio/video transcription.';
        }

        foreach ($required as $message) {
            $checks[] = ['status' => 'FAIL', 'message' => $message];
            $ready = false;
        }

        if (in_array('pdf', $extensions, true) && (!$has('tesseract') || !$has('gs'))) {
            $checks[] = [
                'status' => 'WARN',
                'message' => 'Install Ghostscript and Tesseract (ara+eng) to cover scanned PDFs.',
            ];
        }

        if ($has('tesseract')) {
            try {
                $process = new Process([$finder->find('tesseract'), '--list-langs']);
                $process->setTimeout(30);
                $process->run();
                $languages = $process->getOutput();
                foreach (['ara', 'eng'] as $language) {
                    if (!preg_match('/^'.preg_quote($language, '/').'$/m', $languages)) {
                        $checks[] = ['status' => 'FAIL', 'message' => "Tesseract language '{$language}' is missing."];
                        $ready = false;
                    }
                }
            } catch (Throwable $exception) {
                $checks[] = ['status' => 'WARN', 'message' => 'Unable to inspect Tesseract languages.'];
            }
        }

        if (!extension_loaded('zip')) {
            $checks[] = ['status' => 'FAIL', 'message' => 'PHP zip extension is required for DOCX/PPTX/XLSX.'];
            $ready = false;
        }

        if ($checkRuntime) {
            if (config('ai.provider', 'openai') === 'openai') {
                $key = app(OpenAiConfigResolver::class)->apiKey();
                if ($key === null || trim($key) === '') {
                    $checks[] = ['status' => 'FAIL', 'message' => 'OPENAI_API_KEY is not configured.'];
                    $ready = false;
                } else {
                    $checks[] = ['status' => 'OK', 'message' => 'OpenAI API key is configured (value hidden).'];
                }
            } else {
                $checks[] = ['status' => 'WARN', 'message' => 'Non-OpenAI provider selected; generated vectors are not for production.'];
            }

            try {
                if (!Schema::hasTable('knowledge_documents') || !Schema::hasColumn('knowledge_documents', 'content_hash')) {
                    $checks[] = ['status' => 'FAIL', 'message' => 'Knowledge migrations are missing; run php artisan migrate.'];
                    $ready = false;
                } else {
                    $checks[] = ['status' => 'OK', 'message' => 'Knowledge database schema is ready.'];
                }
            } catch (Throwable $exception) {
                $checks[] = ['status' => 'FAIL', 'message' => 'Database connection failed: '.$exception->getMessage()];
                $ready = false;
            }

            try {
                $storageRoot = Storage::disk('local')->path('knowledge');
                $parent = dirname($storageRoot);
                if ((!is_dir($parent) && !@mkdir($parent, 0775, true)) || !is_writable($parent)) {
                    $checks[] = ['status' => 'FAIL', 'message' => 'storage/app/public is not writable.'];
                    $ready = false;
                } else {
                    $checks[] = ['status' => 'OK', 'message' => 'Knowledge storage is writable.'];
                }
            } catch (Throwable $exception) {
                $checks[] = ['status' => 'FAIL', 'message' => 'Knowledge storage check failed: '.$exception->getMessage()];
                $ready = false;
            }
        }

        if ($checks === []) {
            $checks[] = ['status' => 'OK', 'message' => 'All required local extractors are available.'];
        }

        return ['ready' => $ready, 'checks' => $checks];
    }

    private function importFile(array $file, string $category, bool $force, bool $link): array
    {
        $existing = KnowledgeDocument::withTrashed()
            ->where('content_hash', $file['content_hash'])
            ->first();
        $existing ??= KnowledgeDocument::withTrashed()
            ->where('source_path', $file['relative_path'])
            ->where('category', $category)
            ->first();
        $wasExisting = $existing !== null && !$existing->trashed();
        $contentChanged = $existing !== null && $existing->content_hash !== $file['content_hash'];

        if ($existing !== null && $existing->trashed()) {
            $existing->restore();
        }

        $targetPath = $contentChanged ? null : $existing?->file_path;
        if ($targetPath === null || !Storage::disk('local')->exists($targetPath)) {
            $targetPath = $file['source'] === 'storage' && in_array($file['disk'], ['local', 'public'], true)
                ? $file['path']
                : $this->copyIntoStorage($file, $link);
        }

        $document = $existing ?? new KnowledgeDocument();
        $document->fill([
            'title' => KnowledgeDocument::titleFromFilename($file['original_name']),
            'original_name' => $file['original_name'],
            'file_path' => $targetPath,
            'mime_type' => $file['mime_type'],
            'file_size' => $file['file_size'],
            'content_hash' => $file['content_hash'],
            'source_path' => $file['relative_path'],
            'category' => $category,
            'status' => $document->exists && !$force && !$contentChanged ? $document->status : 'uploaded',
            'uploaded_by' => null,
            'processing_error' => $document->exists && !$force && !$contentChanged ? $document->processing_error : null,
            'processed_at' => $document->exists && !$force && !$contentChanged ? $document->processed_at : null,
            'index_only' => false,
            'ingestion_metadata' => array_merge($document->ingestion_metadata ?? [], [
                'source_path' => $file['relative_path'],
                'source_size' => $file['file_size'],
                'source_modified_at' => $file['modified_at'],
                'storage_mode' => $link ? 'hardlink_or_copy' : 'copy',
            ]),
        ]);
        $document->save();

        if ($force) {
            $document->chunks()->delete();
        }

        return [$document, $wasExisting];
    }

    private function copyIntoStorage(array $file, bool $link): string
    {
        $extension = $file['extension'];
        $targetPath = sprintf(
            'knowledge/imported/%s/%s.%s',
            substr($file['content_hash'], 0, 2),
            $file['content_hash'],
            $extension
        );
        $storage = Storage::disk('local');

        if ($storage->exists($targetPath)) {
            return $targetPath;
        }

        $targetAbsolute = $storage->path($targetPath);
        $directory = dirname($targetAbsolute);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create knowledge storage directory: {$directory}");
        }

        if ($link && @link($file['absolute_path'], $targetAbsolute)) {
            return $targetPath;
        }

        if (!copy($file['absolute_path'], $targetAbsolute)) {
            throw new RuntimeException('Unable to copy source file into knowledge storage.');
        }

        return $targetPath;
    }

    private function writeReport(string $requestedPath, array $analysis): string
    {
        $path = str_starts_with($requestedPath, DIRECTORY_SEPARATOR)
            ? $requestedPath
            : base_path($requestedPath);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create report directory: {$directory}");
        }
        $json = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json."\n") === false) {
            throw new RuntimeException('Unable to write JSON analysis report.');
        }
        return $path;
    }

    private function renderAnalysis(array $analysis): void
    {
        $summary = $analysis['summary'];
        $this->table(['Metric', 'Value'], [
            ['Scanned files', $summary['scanned_files']],
            ['Unique supported files', $summary['unique_supported_files']],
            ['Duplicate copies skipped', $summary['duplicate_files']],
            ['Metadata/temp files skipped', $summary['metadata_or_temporary_files']],
            ['Unsupported files skipped', $summary['unsupported_files']],
            ['Empty files skipped', $summary['empty_files']],
            ['Unique source size', $this->formatBytes($summary['unique_bytes'])],
        ]);

        $rows = [];
        foreach ($summary['unique_by_extension'] as $extension => $count) {
            $rows[] = [$extension, $count];
        }
        $this->table(['Type', 'Unique files'], $rows);
    }

    private function renderPreflight(array $preflight): void
    {
        $this->newLine();
        $this->line('Preflight:');
        foreach ($preflight['checks'] as $check) {
            $method = $check['status'] === 'FAIL' ? 'error' : ($check['status'] === 'WARN' ? 'warn' : 'line');
            $this->{$method}(sprintf('  [%s] %s', $check['status'], $check['message']));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2).' '.$unit;
            }
        }
        return $bytes.' B';
    }
}

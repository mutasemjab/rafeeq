<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DocumentTextExtractor
{
    private const CACHE_VERSION = 4;

    public function extractFromStoragePath(string $filePath, ?string $mimeType = null): array
    {
        return $this->extractFromAbsolutePath(Storage::path($filePath), $mimeType);
    }

    /**
     * @return array<int, array{page: int|null, text: string}>
     */
    public function extractFromAbsolutePath(string $absolutePath, ?string $mimeType = null): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException("File not found or unreadable: {$absolutePath}");
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = strtolower((string) ($mimeType ?? mime_content_type($absolutePath) ?: ''));

        $cachePath = $this->extractionCachePath($absolutePath);
        if ($cachePath !== null && is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (
                is_array($cached) &&
                (int) ($cached['version'] ?? 0) === self::CACHE_VERSION &&
                is_array($cached['pages'] ?? null) &&
                $this->hasMeaningfulText($cached['pages'])
            ) {
                return $cached['pages'];
            }
        }

        $pages = match ($extension) {
            'txt', 'csv', 'md' => $this->extractText($absolutePath),
            'html', 'htm' => $this->extractHtml($absolutePath),
            'pdf' => $this->extractPdf($absolutePath),
            'docx' => $this->extractDocx($absolutePath),
            'doc' => $this->extractLegacyDoc($absolutePath),
            'pptx' => $this->extractPptx($absolutePath),
            'ppt' => $this->extractLegacyPresentation($absolutePath),
            'xlsx' => $this->extractXlsx($absolutePath),
            'xls' => $this->extractLegacySpreadsheet($absolutePath),
            'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff' => $this->extractImage($absolutePath),
            'mp3', 'm4a', 'wav', 'mp4', 'mov', 'm4v', 'avi', 'flv' =>
                app(MediaTranscriber::class)->transcribe($absolutePath),
            default => throw new RuntimeException(
                "Unsupported file type for text extraction: {$extension} ({$mimeType})"
            ),
        };

        if ($cachePath !== null && $this->hasMeaningfulText($pages)) {
            $directory = dirname($cachePath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create the extraction cache directory.');
            }
            file_put_contents($cachePath, json_encode([
                'version' => self::CACHE_VERSION,
                'pages' => $pages,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $pages;
    }

    private function extractText(string $path): array
    {
        $text = file_get_contents($path);

        if ($text === false) {
            throw new RuntimeException('Unable to read the text document.');
        }

        return $this->requireText([['page' => 1, 'text' => $this->normalize($text)]], 'text document');
    }

    private function extractHtml(string $path): array
    {
        $html = file_get_contents($path);

        if ($html === false) {
            throw new RuntimeException('Unable to read the HTML document.');
        }

        $html = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->requireText([['page' => 1, 'text' => $this->normalize($text)]], 'HTML document');
    }

    private function extractPdf(string $path): array
    {
        $strategies = [
            'pdftotext' => fn(): array => $this->extractPdfWithPdftotext($path),
        ];

        if ((filesize($path) ?: PHP_INT_MAX) <= (int) config('ai.pdf_parser_max_bytes', 15 * 1024 * 1024)) {
            $strategies['pdf_parser'] = fn(): array => $this->extractPdfWithParser($path);
        }

        $strategies['ocr'] = fn(): array => $this->extractPdfWithOcr($path);
        $strategies['vision'] = fn(): array => $this->extractPdfWithVision($path);

        foreach ($strategies as $name => $strategy) {
            try {
                $pages = $strategy();

                if ($this->hasMeaningfulText($pages)) {
                    Log::info('knowledge.extractor.pdf_strategy', [
                        'file' => basename($path),
                        'strategy' => $name,
                        'pages' => count($pages),
                    ]);

                    return $name === 'vision'
                        ? $pages
                        : $this->enrichSparsePdfPages($path, $pages);
                }
            } catch (Throwable $exception) {
                Log::warning('knowledge.extractor.pdf_strategy_failed', [
                    'file' => basename($path),
                    'strategy' => $name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('PDF extraction and OCR produced no usable text.');
    }

    protected function extractPdfWithPdftotext(string $path): array
    {
        if (!$this->commandExists('pdftotext')) {
            return [];
        }

        $text = $this->runCommand([
            'pdftotext',
            '-layout',
            '-enc',
            'UTF-8',
            $path,
            '-',
        ], false);

        return $this->splitPages((string) $text);
    }

    protected function extractPdfWithParser(string $path): array
    {
        if (!class_exists(Parser::class)) {
            return [];
        }

        $pages = [];

        foreach ((new Parser())->parseFile($path)->getPages() as $index => $page) {
            $text = $this->normalize((string) $page->getText());

            if ($text !== '') {
                $pages[] = ['page' => $index + 1, 'text' => $text];
            }
        }

        return $pages;
    }

    protected function extractPdfWithOcr(string $path): array
    {
        if (!$this->commandExists('gs') || !$this->commandExists('tesseract')) {
            return [];
        }

        $pageCount = $this->pdfPageCount($path);
        $maxPages = max(1, (int) config('ai.ocr_max_pages', 600));

        if ($pageCount < 1) {
            return [];
        }

        if ($pageCount > $maxPages) {
            throw new RuntimeException("PDF has {$pageCount} pages; OCR safety limit is {$maxPages} pages.");
        }

        $tempDirectory = $this->makeTempDirectory('pdf-ocr');
        $pages = [];

        try {
            for ($page = 1; $page <= $pageCount; $page++) {
                $image = $tempDirectory.DIRECTORY_SEPARATOR.sprintf('page-%04d.png', $page);
                $this->runCommand([
                    'gs',
                    '-q',
                    '-dSAFER',
                    '-dBATCH',
                    '-dNOPAUSE',
                    '-sDEVICE=pnggray',
                    '-r160',
                    "-dFirstPage={$page}",
                    "-dLastPage={$page}",
                    "-sOutputFile={$image}",
                    $path,
                ], false);

                if (!is_file($image)) {
                    continue;
                }

                $text = $this->ocrImage($image);

                if ($text !== '') {
                    $pages[] = ['page' => $page, 'text' => $text];
                }
            }
        } finally {
            $this->deleteDirectory($tempDirectory);
        }

        return $pages;
    }

    protected function extractPdfWithVision(string $path, ?array $selectedPages = null): array
    {
        if (!$this->commandExists('gs')) {
            return [];
        }

        $pageCount = $this->pdfPageCount($path);
        $maxPages = max(1, (int) config('ai.ocr_max_pages', 600));

        if ($pageCount < 1) {
            return [];
        }

        if ($pageCount > $maxPages) {
            throw new RuntimeException("PDF has {$pageCount} pages; vision safety limit is {$maxPages} pages.");
        }

        $tempDirectory = $this->makeTempDirectory('pdf-vision');
        $pages = [];

        try {
            $pagesToRender = $selectedPages ?? range(1, $pageCount);

            foreach ($pagesToRender as $page) {
                $page = (int) $page;
                if ($page < 1 || $page > $pageCount) {
                    continue;
                }
                $image = $tempDirectory.DIRECTORY_SEPARATOR.sprintf('page-%04d.jpg', $page);
                $this->runCommand([
                    'gs',
                    '-q',
                    '-dSAFER',
                    '-dBATCH',
                    '-dNOPAUSE',
                    '-sDEVICE=jpeg',
                    '-dJPEGQ=80',
                    '-r120',
                    "-dFirstPage={$page}",
                    "-dLastPage={$page}",
                    "-sOutputFile={$image}",
                    $path,
                ], false);

                if (!is_file($image)) {
                    continue;
                }

                $text = trim(app(ImageDescriber::class)->describe($image));
                if ($text !== '') {
                    $pages[] = ['page' => $page, 'text' => $text];
                }
            }
        } finally {
            $this->deleteDirectory($tempDirectory);
        }

        return $pages;
    }

    private function extractDocx(string $path): array
    {
        $pages = $this->extractWordOpenXml($path);

        if ($this->hasMeaningfulText($pages)) {
            return $pages;
        }

        if ($this->commandExists('textutil')) {
            $text = $this->runCommand(['textutil', '-convert', 'txt', '-stdout', $path], false);
            return $this->requireText([['page' => 1, 'text' => $this->normalize((string) $text)]], 'DOCX');
        }

        throw new RuntimeException('DOCX extraction produced no usable text.');
    }

    private function extractWordOpenXml(string $path): array
    {
        $zip = $this->openZip($path, 'DOCX');
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (preg_match('#^word/(document|footnotes|endnotes|header\d+|footer\d+)\.xml$#', $name)) {
                $entries[] = $name;
            }
        }

        sort($entries, SORT_NATURAL);
        $blocks = [];

        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);

            if (is_string($xml)) {
                array_push($blocks, ...$this->extractXmlParagraphs(
                    $xml,
                    'w',
                    'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                ));
            }
        }

        $zip->close();
        $text = $this->normalize(implode("\n\n", array_filter($blocks)));

        return $text === '' ? [] : [['page' => 1, 'text' => $text]];
    }

    private function extractLegacyDoc(string $path): array
    {
        if ($this->isEncryptedCompoundOfficeFile($path)) {
            throw new RuntimeException(
                'Legacy Word document is password-protected. Remove the password before ingestion.'
            );
        }

        foreach ([
            ['antiword', $path],
            ['catdoc', '-d', 'utf-8', $path],
        ] as $command) {
            if (!$this->commandExists($command[0])) {
                continue;
            }

            $text = $this->normalize((string) $this->runCommand($command, false));

            if ($this->isPlausibleExtractedText($text)) {
                return [['page' => 1, 'text' => $text]];
            }
        }

        if ($this->commandExists('soffice')) {
            try {
                return $this->convertWithLibreOffice($path, 'pdf', 'pdf', fn(string $converted): array =>
                    $this->extractPdf($converted)
                );
            } catch (Throwable $exception) {
                Log::warning('knowledge.extractor.legacy_doc_libreoffice_failed', [
                    'file' => basename($path),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->commandExists('textutil')) {
            $text = $this->normalize((string) $this->runCommand([
                'textutil', '-convert', 'txt', '-stdout', $path,
            ], false));

            if ($this->isPlausibleExtractedText($text)) {
                return [['page' => 1, 'text' => $text]];
            }
        }

        throw new RuntimeException('Legacy DOC extraction produced no trustworthy text.');
    }

    private function extractPptx(string $path): array
    {
        $zip = $this->openZip($path, 'PPTX');
        $slides = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $matches)) {
                $slides[(int) $matches[1]] = $name;
            }
        }

        ksort($slides);
        $pages = [];

        foreach ($slides as $slideNumber => $entry) {
            $xml = $zip->getFromName($entry);
            $text = is_string($xml) ? $this->extractDrawingText($xml) : '';
            $notesXml = $zip->getFromName("ppt/notesSlides/notesSlide{$slideNumber}.xml");

            if (is_string($notesXml)) {
                $text = trim($text."\n\n".$this->extractDrawingText($notesXml));
            }

            $text = $this->normalize($text);

            if ($text !== '') {
                $pages[] = ['page' => $slideNumber, 'text' => $text];
            }
        }

        $zip->close();

        if ($this->hasMeaningfulText($pages)) {
            return $pages;
        }

        return $this->convertWithLibreOffice($path, 'pdf', 'pdf', fn(string $converted): array =>
            $this->extractPdf($converted)
        );
    }

    private function extractLegacyPresentation(string $path): array
    {
        if ($this->isEncryptedCompoundOfficeFile($path)) {
            throw new RuntimeException(
                'Legacy PowerPoint is password-protected. Remove the password before ingestion.'
            );
        }

        return $this->convertWithLibreOffice($path, 'pptx', 'pptx', fn(string $converted): array =>
            $this->extractPptx($converted)
        );
    }

    private function enrichSparsePdfPages(string $path, array $pages): array
    {
        if (!config('ai.document_vision_fill_sparse_pages', true) || !$this->commandExists('gs')) {
            return $pages;
        }

        $pageCount = $this->pdfPageCount($path);
        if ($pageCount < 1) {
            return $pages;
        }

        $minimumCharacters = max(10, (int) config('ai.document_sparse_page_characters', 80));
        $byPage = [];

        foreach ($pages as $page) {
            $number = max(1, (int) ($page['page'] ?? 1));
            $byPage[$number] = [
                'page' => $number,
                'text' => $this->normalize((string) ($page['text'] ?? '')),
            ];
        }

        $sparsePages = [];
        for ($number = 1; $number <= $pageCount; $number++) {
            $compact = preg_replace('/\s+/u', '', (string) ($byPage[$number]['text'] ?? '')) ?? '';
            if (mb_strlen($compact) < $minimumCharacters) {
                $sparsePages[] = $number;
            }
        }

        if ($sparsePages === []) {
            ksort($byPage);
            return array_values($byPage);
        }

        try {
            foreach ($this->extractPdfWithVision($path, $sparsePages) as $visualPage) {
                $number = (int) ($visualPage['page'] ?? 0);
                $visualText = $this->normalize((string) ($visualPage['text'] ?? ''));
                $existingText = $this->normalize((string) ($byPage[$number]['text'] ?? ''));

                if ($number < 1 || $visualText === '') {
                    continue;
                }

                $byPage[$number] = [
                    'page' => $number,
                    'text' => trim($existingText."\n\nVisual content:\n".$visualText),
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('knowledge.extractor.pdf_sparse_vision_failed', [
                'file' => basename($path),
                'sparse_pages' => count($sparsePages),
                'message' => $exception->getMessage(),
            ]);
        }

        ksort($byPage);
        return array_values(array_filter($byPage, fn(array $page): bool =>
            trim((string) ($page['text'] ?? '')) !== ''
        ));
    }

    private function extractXlsx(string $path): array
    {
        $zip = $this->openZip($path, 'XLSX');
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if (is_string($sharedXml)) {
            $document = $this->loadXml($sharedXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            foreach ($xpath->query('//x:si') ?: [] as $item) {
                $parts = [];
                foreach ($xpath->query('.//x:t', $item) ?: [] as $node) {
                    $parts[] = $node->textContent;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }

        $sheets = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $matches)) {
                $sheets[(int) $matches[1]] = $name;
            }
        }
        ksort($sheets);
        $pages = [];

        foreach ($sheets as $sheetNumber => $entry) {
            $xml = $zip->getFromName($entry);
            if (!is_string($xml)) {
                continue;
            }

            $document = $this->loadXml($xml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];

            foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $row) {
                $cells = [];
                foreach ($xpath->query('./x:c', $row) ?: [] as $cell) {
                    $type = $cell->attributes?->getNamedItem('t')?->nodeValue;
                    $valueNode = $xpath->query('./x:v', $cell)?->item(0);
                    $inlineNode = $xpath->query('./x:is/x:t', $cell)?->item(0);
                    $value = $inlineNode?->textContent ?? $valueNode?->textContent ?? '';

                    if ($type === 's' && ctype_digit((string) $value)) {
                        $value = $sharedStrings[(int) $value] ?? $value;
                    }

                    if (trim((string) $value) !== '') {
                        $cells[] = trim((string) $value);
                    }
                }

                if ($cells !== []) {
                    $rows[] = implode(' | ', $cells);
                }
            }

            $text = $this->normalize(implode("\n", $rows));
            if ($text !== '') {
                $pages[] = ['page' => $sheetNumber, 'text' => $text];
            }
        }

        $zip->close();

        return $this->requireText($pages, 'XLSX');
    }

    private function extractLegacySpreadsheet(string $path): array
    {
        return $this->convertWithLibreOffice($path, 'xlsx', 'xlsx', fn(string $converted): array =>
            $this->extractXlsx($converted)
        );
    }

    private function extractImage(string $path): array
    {
        try {
            $text = $this->ocrImage($path);
        } catch (Throwable) {
            $text = '';
        }

        if (mb_strlen(preg_replace('/\s+/u', '', $text) ?? $text) < 3) {
            $text = app(ImageDescriber::class)->describe($path);
        }

        return $this->requireText([['page' => 1, 'text' => $text]], 'image extraction');
    }

    protected function ocrImage(string $path): string
    {
        if (!$this->commandExists('tesseract')) {
            throw new RuntimeException('Tesseract is required for image and scanned-PDF OCR.');
        }

        return $this->normalize((string) $this->runCommand([
            'tesseract',
            $path,
            'stdout',
            '-l',
            (string) config('ai.ocr_languages', 'ara+eng'),
            '--psm',
            '3',
        ], false));
    }

    private function convertWithLibreOffice(
        string $path,
        string $format,
        string $extension,
        callable $extractor
    ): array {
        if (!$this->commandExists('soffice')) {
            throw new RuntimeException(
                'LibreOffice (soffice) is required for legacy DOC/PPT/XLS conversion.'
            );
        }

        $tempDirectory = $this->makeTempDirectory('office');

        try {
            $this->runCommand([
                'soffice',
                '--headless',
                '--convert-to',
                $format,
                '--outdir',
                $tempDirectory,
                $path,
            ]);

            $matches = glob($tempDirectory.DIRECTORY_SEPARATOR.'*.'.$extension) ?: [];

            if ($matches === []) {
                throw new RuntimeException("LibreOffice did not create a .{$extension} conversion.");
            }

            return $extractor($matches[0]);
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    private function extractDrawingText(string $xml): string
    {
        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $parts = [];

        foreach ($xpath->query('//a:t') ?: [] as $node) {
            $parts[] = $node->textContent;
        }

        return implode("\n", $parts);
    }

    private function extractXmlParagraphs(string $xml, string $prefix, string $namespace): array
    {
        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace($prefix, $namespace);
        $paragraphs = [];

        foreach ($xpath->query("//{$prefix}:p") ?: [] as $paragraph) {
            $parts = [];
            foreach ($xpath->query(".//{$prefix}:t", $paragraph) ?: [] as $node) {
                $parts[] = $node->textContent;
            }
            $text = trim(implode('', $parts));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs;
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('Unable to parse document XML.');
        }

        return $document;
    }

    private function openZip(string $path, string $type): ZipArchive
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("The PHP zip extension is required for {$type} extraction.");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open {$type} file.");
        }
        return $zip;
    }

    private function splitPages(string $text): array
    {
        $pages = [];
        foreach (preg_split('/\f/u', $text) ?: [$text] as $index => $pageText) {
            $normalized = $this->normalize($pageText);
            if ($normalized !== '') {
                $pages[] = ['page' => $index + 1, 'text' => $normalized];
            }
        }
        return $pages;
    }

    private function pdfPageCount(string $path): int
    {
        if (!$this->commandExists('pdfinfo')) {
            return 0;
        }
        $output = (string) $this->runCommand(['pdfinfo', $path], false);
        return preg_match('/^Pages:\s+(\d+)/mi', $output, $matches) ? (int) $matches[1] : 0;
    }

    private function requireText(array $pages, string $type): array
    {
        $pages = array_values(array_filter($pages, fn(array $page): bool =>
            trim((string) ($page['text'] ?? '')) !== ''
        ));

        if (!$this->hasMeaningfulText($pages)) {
            throw new RuntimeException("{$type} extraction produced no usable text.");
        }

        return $pages;
    }

    private function hasMeaningfulText(array $pages): bool
    {
        $text = trim(implode(' ', array_map(
            fn(array $page): string => (string) ($page['text'] ?? ''),
            $pages
        )));
        return mb_strlen(preg_replace('/\s+/u', '', $text) ?? $text) >= 3;
    }

    protected function commandExists(string $command): bool
    {
        return (new ExecutableFinder())->find($command) !== null;
    }

    protected function runCommand(array $command, bool $throwOnFailure = true): ?string
    {
        $resolved = (new ExecutableFinder())->find($command[0]);
        if ($resolved !== null) {
            $command[0] = $resolved;
        }

        $process = new Process($command);
        $process->setTimeout(max(30, (int) config('ai.document_extraction_command_timeout', 900)));
        $process->run();

        if ($throwOnFailure && !$process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Document conversion command failed.');
        }

        return $process->getOutput();
    }

    private function makeTempDirectory(string $prefix): string
    {
        $base = storage_path('app/knowledge-tmp');
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new RuntimeException('Unable to create the knowledge temporary directory.');
        }
        $path = $base.DIRECTORY_SEPARATOR.$prefix.'-'.bin2hex(random_bytes(8));
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create a temporary extraction directory.');
        }
        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }

    private function isPlausibleExtractedText(string $text): bool
    {
        $text = $this->normalize($text);
        $length = mb_strlen($text);

        if ($length < 3) {
            return false;
        }

        $controlCharacters = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text);
        if ($controlCharacters === false || $controlCharacters > max(4, (int) floor($length * 0.01))) {
            return false;
        }

        $noise = preg_replace('/[\p{L}\p{M}\p{N}\p{P}\p{Z}\s]/u', '', $text);
        if ($noise === null) {
            return false;
        }

        return mb_strlen($noise) <= max(10, (int) floor($length * 0.20));
    }

    private function isEncryptedCompoundOfficeFile(string $path): bool
    {
        $contents = file_get_contents($path);

        if ($contents === false || !str_starts_with($contents, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return false;
        }

        foreach (['EncryptedSummary', 'EncryptedPackage'] as $streamName) {
            $utf16Name = mb_convert_encoding($streamName, 'UTF-16LE', 'UTF-8');
            if (str_contains($contents, $utf16Name)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1256, Windows-1252, ISO-8859-1');
        }
        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function extractionCachePath(string $path): ?string
    {
        if (!config('ai.document_extraction_cache', true)) {
            return null;
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            return null;
        }

        return storage_path('app/knowledge-extraction-cache/'.substr($hash, 0, 2).'/'.$hash.'.json');
    }
}

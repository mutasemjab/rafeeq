<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentTextExtractor
{
    public function extractFromStoragePath(string $filePath, ?string $mimeType = null): array
    {
        $absolutePath = Storage::path($filePath);
        return $this->extractFromAbsolutePath($absolutePath, $mimeType);
    }

    public function extractFromAbsolutePath(string $absolutePath, ?string $mimeType = null): array
    {
        if (!file_exists($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType  = (string) ($mimeType ?? mime_content_type($absolutePath) ?: '');

        if ($extension === 'txt' || str_contains($mimeType, 'text/plain')) {
            return $this->extractTxt($absolutePath);
        }

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return $this->extractPdf($absolutePath);
        }

        if (in_array($extension, ['docx', 'doc']) || str_contains($mimeType, 'wordprocessingml')) {
            return $this->extractDocx($absolutePath);
        }

        if ($extension === 'pptx' || str_contains($mimeType, 'presentationml') || str_contains($mimeType, 'powerpoint')) {
            return $this->extractPptx($absolutePath);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new RuntimeException('OCR is not implemented yet for image files.');
        }

        throw new RuntimeException("Unsupported file type: {$extension}");
    }

    private function extractTxt(string $path): array
    {
        $text = file_get_contents($path);
        $text = $this->normalize($text);
        if (empty($text)) {
            throw new RuntimeException('Document text is empty.');
        }
        return [['page' => 1, 'text' => $text]];
    }

    private function extractPdf(string $path): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('smalot/pdfparser is required: composer require smalot/pdfparser');
        }
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($path);
        $pages  = $pdf->getPages();
        $result = [];
        foreach ($pages as $index => $page) {
            $text = $this->normalize($page->getText());
            if (!empty($text)) {
                $result[] = ['page' => $index + 1, 'text' => $text];
            }
        }
        if (empty($result)) {
            throw new RuntimeException('PDF extracted no usable text.');
        }
        return $result;
    }

    private function extractDocx(string $path): array
    {
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new RuntimeException('phpoffice/phpword is required: composer require phpoffice/phpword');
        }
        $word     = \PhpOffice\PhpWord\IOFactory::load($path);
        $fullText = '';
        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $fullText .= $child->getText() . ' ';
                        }
                    }
                }
            }
        }
        $fullText = $this->normalize($fullText);
        if (empty($fullText)) {
            throw new RuntimeException('DOCX extracted no usable text.');
        }
        return [['page' => null, 'text' => $fullText]];
    }

    private function extractPptx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to process PPTX files.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open PPTX file.');
        }

        $slides = [];
        $notes  = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $entryName, $matches)) {
                $slides[(int) $matches[1]] = $entryName;
            }

            if (preg_match('#^ppt/notesSlides/notesSlide(\d+)\.xml$#', $entryName, $matches)) {
                $notes[(int) $matches[1]] = $entryName;
            }
        }

        ksort($slides);
        ksort($notes);

        $result = [];

        foreach ($slides as $slideNumber => $slideEntry) {
            $slideText = $this->extractOpenXmlText($zip->getFromName($slideEntry) ?: '');
            $notesText = isset($notes[$slideNumber])
                ? $this->extractOpenXmlText($zip->getFromName($notes[$slideNumber]) ?: '')
                : '';

            $text = $this->normalize(trim($slideText . ' ' . $notesText));

            if ($text !== '') {
                $result[] = [
                    'page' => $slideNumber,
                    'text' => $text,
                ];
            }
        }

        $zip->close();

        if (empty($result)) {
            throw new RuntimeException('PPTX extracted no usable text.');
        }

        return $result;
    }

    private function extractOpenXmlText(string $xml): string
    {
        if ($xml === '') {
            return '';
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return trim(strip_tags($xml));
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $parts = [];

        foreach (['//a:t', '//w:t'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $parts[] = $node->textContent;
            }
        }

        return implode(' ', $parts);
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}

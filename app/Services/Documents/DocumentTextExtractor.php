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
        $mimeType  = $mimeType ?? mime_content_type($absolutePath);

        if ($extension === 'txt' || str_contains($mimeType, 'text/plain')) {
            return $this->extractTxt($absolutePath);
        }

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return $this->extractPdf($absolutePath);
        }

        if (in_array($extension, ['docx', 'doc']) || str_contains($mimeType, 'wordprocessingml')) {
            return $this->extractDocx($absolutePath);
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

    private function normalize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}

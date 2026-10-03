<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class DocumentTextExtractorQualityTest extends TestCase
{
    public function test_binary_converter_output_is_not_accepted_as_document_text(): void
    {
        $extractor = app(DocumentTextExtractor::class);
        $method = new ReflectionMethod($extractor, 'isPlausibleExtractedText');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(
            $extractor,
            str_repeat("\x01\x02\x03\x04\x05\x06\x07\x08", 100).str_repeat('√ƒ≈∆', 100)
        ));
        $this->assertTrue($method->invoke(
            $extractor,
            'تقييم النطق واللغة يحتوي على كلمات عربية واضحة ومعلومات قابلة للبحث.'
        ));
    }

    public function test_password_protected_legacy_powerpoint_fails_with_an_actionable_message(): void
    {
        $path = sys_get_temp_dir().'/rafeeq-encrypted-'.bin2hex(random_bytes(6)).'.ppt';
        $marker = mb_convert_encoding('EncryptedSummary', 'UTF-16LE', 'UTF-8');
        file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 64).$marker);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('password-protected');
            app(DocumentTextExtractor::class)->extractFromAbsolutePath($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_private_extraction_neither_reads_nor_writes_shared_knowledge_cache(): void
    {
        config(['ai.document_extraction_cache' => true, 'filesystems.default' => 'public']);
        Storage::fake('public');
        $file = 'private-report.txt';
        $text = 'This private assessment reports the child development observations and caregiver history. '.bin2hex(random_bytes(8));
        Storage::disk('public')->put($file, $text);
        $path = Storage::disk('public')->path($file);
        $hash = hash_file('sha256', $path);
        $cachePath = storage_path('app/knowledge-extraction-cache/'.substr($hash, 0, 2).'/'.$hash.'.json');
        $extractor = app(DocumentTextExtractor::class);

        try {
            $this->assertSame($text, $extractor->extractFromAbsolutePath($path, 'text/plain', false)[0]['text']);
            $this->assertFileDoesNotExist($cachePath);
            $this->assertSame($text, $extractor->extractFromStoragePath($file, 'text/plain', false)[0]['text']);
            $this->assertFileDoesNotExist($cachePath);

            // Public knowledge extraction can still cache. Private calls must
            // ignore even a pre-existing shared cache for identical bytes.
            $extractor->extractFromAbsolutePath($path, 'text/plain');
            $cached = json_decode(file_get_contents($cachePath), true);
            $cached['pages'][0]['text'] = 'A stale shared cached assessment that must not be read for this private file.';
            file_put_contents($cachePath, json_encode($cached));

            $this->assertSame($text, $extractor->extractFromAbsolutePath($path, 'text/plain', false)[0]['text']);
            $this->assertSame($text, $extractor->extractFromStoragePath($file, 'text/plain', false)[0]['text']);
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }
}

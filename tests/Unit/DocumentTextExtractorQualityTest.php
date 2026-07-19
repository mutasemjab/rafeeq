<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentTextExtractor;
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
}

<?php

namespace Tests\Unit;

use App\Models\KnowledgeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class KnowledgeDocumentUploadValidationTest extends TestCase
{
    public function test_validation_accepts_supported_extension_even_when_mime_is_generic(): void
    {
        $validator = Validator::make([
            'file' => UploadedFile::fake()->create('language-guide.pdf', 128, 'application/octet-stream'),
        ], [
            'file' => KnowledgeDocument::uploadRules(),
        ]);

        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray()));
    }

    public function test_validation_rejects_legacy_powerpoint_files_with_clear_message(): void
    {
        $validator = Validator::make([
            'file' => UploadedFile::fake()->create('training-deck.ppt', 128, 'application/vnd.ms-powerpoint'),
        ], [
            'file' => KnowledgeDocument::uploadRules(),
        ]);

        $this->assertFalse($validator->passes());
        $this->assertSame(
            'Legacy PowerPoint .ppt files are not supported. Please save the file as .pptx and upload it again.',
            $validator->errors()->first('file')
        );
    }
}

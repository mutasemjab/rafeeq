<?php

namespace Tests\Unit;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\User;
use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProcessChildDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_extracts_uploaded_text_from_the_correct_disk_without_embeddings(): void
    {
        $document = $this->document();
        Storage::disk('public')->put($document->file_path, 'Document text');
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()
            ->with(realpath(Storage::disk('public')->path($document->file_path)), 'text/plain', false)
            ->andReturn([['page' => 1, 'text' => 'الطفل عمره ثلاث سنوات والتشخيص مذكور بالتقرير.']]);

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $document->refresh();
        $this->assertSame('processed', $document->status);
        $this->assertSame('الطفل عمره ثلاث سنوات والتشخيص مذكور بالتقرير.', $document->metadata['extracted_text']);
        $this->assertSame('preserved', $document->metadata['existing_key']);
        $this->assertNotNull($document->processed_at);
        $this->assertNull($document->processing_error);
    }

    public function test_long_reports_preserve_the_beginning_and_conclusion_with_bounded_storage(): void
    {
        $document = $this->document();
        Storage::disk('public')->put($document->file_path, 'Document text');
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andReturn([
            ['page' => 1, 'text' => 'History '.str_repeat('أ', 40000).' Assessment conclusion'],
        ]);

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $metadata = $document->fresh()->metadata;
        $this->assertTrue($metadata['extracted_text_truncated']);
        $this->assertLessThanOrEqual(24000, mb_strlen($metadata['extracted_text']));
        $this->assertStringStartsWith('History ', $metadata['extracted_text']);
        $this->assertStringEndsWith('Assessment conclusion', $metadata['extracted_text']);
    }

    public function test_unreadable_or_outside_storage_document_is_failed_without_leaking_text(): void
    {
        $document = $this->document();
        $document->update(['file_path' => '../../outside.txt']);
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->never();

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $this->assertSame('failed', $document->fresh()->status);
        $this->assertArrayNotHasKey('extracted_text', $document->fresh()->metadata);
    }

    public function test_deleted_documents_are_not_processed(): void
    {
        $document = $this->document();
        $document->delete();
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->never();

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $this->assertSame('uploaded', ChildDocument::withTrashed()->find($document->id)->status);
    }

    public function test_withdrawn_consent_prevents_extraction_by_an_already_queued_job(): void
    {
        $document = $this->document();
        User::find($document->user_id)->update(['ai_consent_accepted_at' => null]);
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->never();

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $this->assertSame('uploaded', $document->fresh()->status);
    }

    public function test_consent_withdrawal_during_extraction_discards_private_text_without_stuck_processing(): void
    {
        $document = $this->document();
        Storage::disk('public')->put($document->file_path, 'Document text');
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andReturnUsing(function () use ($document) {
            User::find($document->user_id)->update(['ai_consent_accepted_at' => null]);

            return [['page' => 1, 'text' => 'Private extracted child assessment.']];
        });

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $this->assertSame('uploaded', $document->fresh()->status);
        $this->assertArrayNotHasKey('extracted_text', $document->fresh()->metadata);
        $this->assertNull($document->fresh()->processed_at);
    }

    public function test_deletion_during_extraction_does_not_restore_text_to_deleted_record(): void
    {
        $document = $this->document();
        Storage::disk('public')->put($document->file_path, 'Document text');
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andReturnUsing(function () use ($document) {
            $document->delete();

            return [['page' => 1, 'text' => 'Private extracted child assessment.']];
        });

        (new ProcessChildDocumentJob($document->id))->handle($extractor);

        $this->assertSoftDeleted($document);
        $this->assertArrayNotHasKey('extracted_text', ChildDocument::withTrashed()->find($document->id)->metadata);
    }

    public function test_backfill_queues_only_missing_text_unless_failed_retry_is_requested(): void
    {
        Queue::fake();
        $uploaded = $this->document();
        $failed = $this->document();
        $failed->update(['status' => 'failed']);
        $processed = $this->document();
        $processed->update(['status' => 'processed', 'metadata' => ['extracted_text' => 'Already extracted']]);

        $this->artisan('child-documents:process')->assertSuccessful();

        Queue::assertPushed(ProcessChildDocumentJob::class, 1);
        Queue::assertPushed(ProcessChildDocumentJob::class, fn ($job) => $job->documentId === $uploaded->id);
        Queue::fake();

        $this->artisan('child-documents:process', ['--id' => $failed->id, '--retry-failed' => true])->assertSuccessful();

        Queue::assertPushed(ProcessChildDocumentJob::class, 1);
        Queue::assertPushed(ProcessChildDocumentJob::class, fn ($job) => $job->documentId === $failed->id);
    }

    private function document(): ChildDocument
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);

        return ChildDocument::create([
            'child_id' => $child->id,
            'user_id' => $user->id,
            'original_name' => 'assessment.txt',
            'file_path' => "children/{$child->id}/assessment.txt",
            'mime_type' => 'text/plain',
            'status' => 'uploaded',
            'metadata' => ['existing_key' => 'preserved'],
        ]);
    }
}

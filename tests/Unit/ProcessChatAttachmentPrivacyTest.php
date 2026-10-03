<?php

namespace Tests\Unit;

use App\Jobs\ProcessChatAttachmentJob;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessChatAttachmentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_attachment_extraction_disables_shared_knowledge_cache(): void
    {
        config(['ai.embedding_dimensions' => 2]);
        Storage::fake('private');
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $attachment = ChatAttachment::factory()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'status' => 'uploaded',
            'storage_disk' => 'private',
        ]);
        Storage::disk('private')->put($attachment->file_path, 'Private report.');
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()
            ->with(realpath(Storage::disk('private')->path($attachment->file_path)), $attachment->mime_type, false)
            ->andReturn([['page' => 1, 'text' => 'This private child assessment describes communication and caregiver observations.']]);
        $this->app->instance(DocumentTextExtractor::class, $extractor);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embeddingMany')->once()->andReturn([[0.1, 0.2]]);
        $this->app->instance(LlmProviderInterface::class, $llm);

        (new ProcessChatAttachmentJob($attachment->id))->handle();

        $this->assertSame('processed', $attachment->fresh()->status);
        $this->assertSame(1, $attachment->chunks()->count());
    }

    public function test_withdrawn_consent_and_deleted_parent_skip_queued_processing(): void
    {
        $attachment = $this->attachment();
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldNotReceive('extractFromAbsolutePath');
        $this->app->instance(DocumentTextExtractor::class, $extractor);
        $attachment->user->update(['ai_consent_accepted_at' => null]);
        (new ProcessChatAttachmentJob($attachment->id))->handle();
        $this->assertSame('uploaded', $attachment->fresh()->status);

        $attachment->user->update(['ai_consent_accepted_at' => now()]);
        $attachment->conversation->delete();
        (new ProcessChatAttachmentJob($attachment->id))->handle();
        $this->assertSame('uploaded', $attachment->fresh()->status);
        $this->assertSame(0, $attachment->chunks()->count());
    }

    public function test_deletion_during_extraction_never_creates_chunks_or_calls_embeddings(): void
    {
        $attachment = $this->attachment();
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andReturnUsing(function () use ($attachment) {
            $attachment->delete();

            return [['page' => 1, 'text' => 'Private child assessment information.']];
        });
        $this->app->instance(DocumentTextExtractor::class, $extractor);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldNotReceive('embeddingMany');
        $this->app->instance(LlmProviderInterface::class, $llm);

        (new ProcessChatAttachmentJob($attachment->id))->handle();

        $this->assertSoftDeleted($attachment);
        $this->assertSame(0, $attachment->chunks()->count());
    }

    public function test_consent_withdrawn_during_embedding_discards_vectors_and_resets_processing(): void
    {
        config(['ai.embedding_dimensions' => 2]);
        $attachment = $this->attachment();
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andReturn([['page' => 1, 'text' => 'Private assessment.']]);
        $this->app->instance(DocumentTextExtractor::class, $extractor);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embeddingMany')->once()->andReturnUsing(function () use ($attachment) {
            $attachment->user->update(['ai_consent_accepted_at' => null]);

            return [[0.1, 0.2]];
        });
        $this->app->instance(LlmProviderInterface::class, $llm);

        (new ProcessChatAttachmentJob($attachment->id))->handle();

        $this->assertSame('uploaded', $attachment->fresh()->status);
        $this->assertNull($attachment->fresh()->processed_at);
        $this->assertSame(0, $attachment->chunks()->count());
    }

    public function test_failed_extraction_does_not_persist_raw_provider_or_path_errors(): void
    {
        $attachment = $this->attachment();
        $extractor = Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->andThrow(new RuntimeException('Private patient text at /private/server/secret; key=secret'));
        $this->app->instance(DocumentTextExtractor::class, $extractor);
        (new ProcessChatAttachmentJob($attachment->id))->handle();
        $this->assertSame('failed', $attachment->fresh()->status);
        $this->assertStringNotContainsString('secret', $attachment->fresh()->processing_error);
        $this->assertStringNotContainsString('patient', $attachment->fresh()->processing_error);
    }

    private function attachment(): ChatAttachment
    {
        Storage::fake('private');
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $attachment = ChatAttachment::factory()->create([
            'user_id' => $user->id, 'conversation_id' => $conversation->id,
            'status' => 'uploaded', 'storage_disk' => 'private',
        ]);
        Storage::disk('private')->put($attachment->file_path, 'Private report.');

        return $attachment;
    }
}

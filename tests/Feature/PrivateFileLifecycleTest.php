<?php

namespace Tests\Feature;

use App\Jobs\ProcessChatAttachmentJob;
use App\Models\ChatAttachment;
use App\Models\ChatAttachmentChunk;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PrivateFileLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('public');
        Storage::fake('private');
        Bus::fake();
    }

    public function test_upload_is_private_and_processing_is_dispatched_after_response_on_sync_queue(): void
    {
        $user = $this->user();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $response = $this->actingAs($user, 'user-api')->postJson('/api/v1/attachments', [
            'conversation_id' => $conversation->id,
            'file' => UploadedFile::fake()->createWithContent('report.txt', 'Private report content.'),
        ])->assertCreated()->assertJsonPath('status', 'uploaded')->assertJsonPath('processing_error', null);
        $attachment = ChatAttachment::findOrFail($response->json('id'));

        $this->assertSame('private', $attachment->storage_disk);
        Storage::disk('private')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertStringContainsString('signature=', $response->json('file_url'));
        $this->assertStringNotContainsString('/storage/', $response->json('file_url'));
        Bus::assertDispatchedAfterResponse(ProcessChatAttachmentJob::class, 1);
        $this->assertCount(0, Bus::dispatched(ProcessChatAttachmentJob::class));
    }

    public function test_authenticated_download_is_owner_only(): void
    {
        $attachment = $this->attachment();
        $path = '/api/v1/attachments/'.$attachment->id.'/download';
        $this->getJson($path)->assertUnauthorized();
        $this->actingAs($this->user(), 'user-api')->getJson($path)->assertForbidden();
        $this->actingAs($attachment->user, 'user-api')->get($path)->assertOk()->assertDownload('report.txt');
    }

    public function test_external_download_link_is_short_lived_signed_and_invalid_when_parent_is_deleted(): void
    {
        $attachment = $this->attachment();
        $url = app(PrivateDocumentStorage::class)->temporaryUrl($attachment);
        $this->get($url)->assertOk()->assertDownload('report.txt');
        $this->get($url.'&owner=999999')->assertForbidden();
        $expired = URL::temporarySignedRoute('attachments.temporary-download', now()->subMinute(), [
            'attachment' => $attachment->id, 'owner' => $attachment->user_id,
        ]);
        $this->get($expired)->assertForbidden();
        $attachment->conversation->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_signed_links_cannot_download_after_child_soft_deletion(): void
    {
        $document = $this->document();
        $url = app(PrivateDocumentStorage::class)->temporaryUrl($document);
        $this->get($url)->assertOk()->assertDownload('report.txt');
        $document->child->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_deleting_attachment_removes_private_and_legacy_files_and_derived_chunks(): void
    {
        $attachment = $this->attachment();
        $attachment->update(['has_legacy_public_copy' => true]);
        Storage::disk('public')->put($attachment->file_path, 'Legacy copy');
        ChatAttachmentChunk::create([
            'chat_attachment_id' => $attachment->id, 'conversation_id' => $attachment->conversation_id,
            'user_id' => $attachment->user_id, 'chunk_index' => 0, 'content' => 'Private extracted text',
            'embedding' => '[0.1,0.2]', 'embedding_dimensions' => 2,
        ]);
        $url = app(PrivateDocumentStorage::class)->temporaryUrl($attachment);

        $this->actingAs($attachment->user, 'user-api')->deleteJson('/api/v1/attachments/'.$attachment->id)->assertOk();

        Storage::disk('private')->assertMissing($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseMissing('chat_attachment_chunks', ['chat_attachment_id' => $attachment->id]);
        $this->assertSoftDeleted('chat_attachments', ['id' => $attachment->id]);
        $this->get($url)->assertNotFound();
    }

    public function test_deleting_child_document_removes_bytes_and_extracted_text(): void
    {
        $document = $this->document();
        $document->update(['metadata' => ['extracted_text' => 'Private extracted text', 'other' => 'retained']]);
        $this->actingAs($document->user, 'user-api')
            ->deleteJson('/api/v1/children/'.$document->child_id.'/documents/'.$document->id)->assertOk();
        Storage::disk('private')->assertMissing($document->file_path);
        $this->assertArrayNotHasKey('extracted_text', ChildDocument::withTrashed()->find($document->id)->metadata);
    }

    public function test_private_file_deletion_also_removes_its_exact_legacy_extraction_cache(): void
    {
        $attachment = $this->attachment();
        $originalStorage = $this->app->storagePath();
        $temporaryStorage = sys_get_temp_dir().'/rafeeq-cache-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($temporaryStorage);
        try {
            $hash = hash_file('sha256', Storage::disk('private')->path($attachment->file_path));
            $cache = storage_path('app/knowledge-extraction-cache/'.substr($hash, 0, 2).'/'.$hash.'.json');
            File::ensureDirectoryExists(dirname($cache));
            File::put($cache, '{"pages":[{"text":"private old extraction"}]}');
            $unrelated = dirname($cache).'/unrelated.json';
            File::put($unrelated, '{"pages":[]}');

            app(PrivateDocumentStorage::class)->delete($attachment);

            $this->assertFileDoesNotExist($cache);
            $this->assertFileExists($unrelated);
            Storage::disk('private')->assertMissing($attachment->file_path);
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }

    public function test_retry_is_owned_failed_only_and_returns_attachment_object_without_raw_error(): void
    {
        $attachment = $this->attachment();
        $attachment->update(['status' => 'failed', 'processing_error' => 'secret API key or absolute server path']);
        $path = '/api/v1/attachments/'.$attachment->id.'/retry';
        $this->actingAs($this->user(), 'user-api')->postJson($path)->assertForbidden();
        $this->actingAs($attachment->user, 'user-api')->getJson('/api/v1/conversations/'.$attachment->conversation_id.'/attachments')
            ->assertOk()->assertDontSee('secret API key');
        $this->postJson($path)->assertOk()->assertJsonPath('id', $attachment->id)
            ->assertJsonPath('status', 'uploaded')->assertJsonPath('processing_error', null)->assertJsonPath('can_retry', false);
        $this->postJson($path)->assertStatus(409);
        Bus::assertDispatchedAfterResponse(ProcessChatAttachmentJob::class, 1);
    }

    public function test_retry_uses_durable_queue_and_requires_current_consent(): void
    {
        config(['queue.default' => 'database']);
        $attachment = $this->attachment();
        $attachment->update(['status' => 'failed']);
        $this->actingAs($attachment->user, 'user-api')->postJson('/api/v1/attachments/'.$attachment->id.'/retry')->assertOk();
        Bus::assertDispatched(ProcessChatAttachmentJob::class, 1);
        Bus::assertNotDispatchedAfterResponse(ProcessChatAttachmentJob::class);
        $attachment->update(['status' => 'failed']);
        $attachment->user->update(['ai_consent_accepted_at' => null]);
        $this->actingAs($attachment->user->fresh(), 'user-api')->postJson('/api/v1/attachments/'.$attachment->id.'/retry')->assertForbidden();
    }

    public function test_backfill_preserves_originals_and_verifies_private_copy_before_switching_disks(): void
    {
        $attachment = $this->attachment();
        $attachment->update(['storage_disk' => 'public']);
        Storage::disk('private')->delete($attachment->file_path);
        Storage::disk('public')->put($attachment->file_path, 'Private legacy file');
        $this->artisan('private-files:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('public', $attachment->fresh()->storage_disk);
        Storage::disk('private')->assertMissing($attachment->file_path);
        $this->artisan('private-files:backfill')->assertSuccessful();
        $this->assertSame('private', $attachment->fresh()->storage_disk);
        $this->assertTrue($attachment->fresh()->has_legacy_public_copy);
        $this->assertSame('Private legacy file', Storage::disk('private')->get($attachment->file_path));
        Storage::disk('public')->assertExists($attachment->file_path);
        $this->artisan('private-files:backfill')->assertSuccessful();
    }

    public function test_traversal_path_is_never_served_or_copied(): void
    {
        $attachment = $this->attachment();
        $attachment->update(['file_path' => '../../.env']);
        $this->get(app(PrivateDocumentStorage::class)->temporaryUrl($attachment))->assertNotFound();
        $attachment->update(['storage_disk' => 'public']);
        $this->artisan('private-files:backfill')->assertFailed();
        $this->assertSame('public', $attachment->fresh()->storage_disk);
    }

    public function test_backfill_does_not_overwrite_an_existing_mismatched_private_file(): void
    {
        $attachment = $this->attachment();
        $attachment->update(['storage_disk' => 'public']);
        Storage::disk('public')->put($attachment->file_path, 'Original legacy file');
        $this->artisan('private-files:backfill')->assertFailed();
        $this->assertSame('public', $attachment->fresh()->storage_disk);
        $this->assertSame('Private report.', Storage::disk('private')->get($attachment->file_path));
        $this->assertSame('Original legacy file', Storage::disk('public')->get($attachment->file_path));
    }

    private function user(): User
    {
        return User::factory()->create(['ai_consent_accepted_at' => now()]);
    }

    private function attachment(): ChatAttachment
    {
        $user = $this->user();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $attachment = ChatAttachment::factory()->create([
            'user_id' => $user->id, 'conversation_id' => $conversation->id, 'original_name' => 'report.txt',
            'file_path' => "chat-attachments/{$user->id}/{$conversation->id}/report.txt", 'storage_disk' => 'private',
        ]);
        Storage::disk('private')->put($attachment->file_path, 'Private report.');

        return $attachment;
    }

    private function document(): ChildDocument
    {
        $user = $this->user();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $document = ChildDocument::create([
            'user_id' => $user->id, 'child_id' => $child->id, 'original_name' => 'report.txt',
            'file_path' => "children/{$child->id}/documents/report.txt", 'storage_disk' => 'private', 'status' => 'uploaded',
        ]);
        Storage::disk('private')->put($document->file_path, 'Private report.');

        return $document;
    }
}

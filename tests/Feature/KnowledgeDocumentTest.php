<?php

namespace Tests\Feature;

use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\Admin;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
    }

    public function test_admin_panel_upload_uses_uploaded_status_and_queues_processing(): void
    {
        $admin = Admin::query()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->actingAs($admin, 'admin')->post('/en/admin/knowledge', [
            'category' => 'Language and Children with Intellectual Disabilities',
            'file'     => UploadedFile::fake()->create('Language_and_Children_with_Intellectual_Disabilities.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(302)->assertSessionHas('success');

        $this->assertDatabaseHas('knowledge_documents', [
            'title'       => 'Language and Children with Intellectual Disabilities',
            'status'      => 'uploaded',
            'uploaded_by' => null,
        ]);

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
    }

    public function test_admin_panel_json_upload_returns_document_payload_for_live_batch_tracking(): void
    {
        $admin = Admin::query()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->actingAs($admin, 'admin')->post('/en/admin/knowledge', [
            'file'     => UploadedFile::fake()->create('Quarterly_Knowledge_Deck.pptx', 100, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            'category' => 'Training',
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('title', 'Quarterly Knowledge Deck')
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('category', 'Training');

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
    }

    public function test_api_admin_upload_uses_uploaded_status(): void
    {
        $adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($adminUser, 'user-api')
            ->postJson('/api/v1/knowledge', [
                'title'    => 'Knowledge Guide',
                'category' => 'Speech',
                'file'     => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'uploaded');

        $this->assertDatabaseHas('knowledge_documents', [
            'title'       => 'Knowledge Guide',
            'status'      => 'uploaded',
            'uploaded_by' => $adminUser->id,
        ]);

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
    }

    public function test_admin_reprocess_resets_document_back_to_uploaded(): void
    {
        $admin = Admin::query()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $document = KnowledgeDocument::query()->create([
            'title'            => 'Existing Document',
            'category'         => 'Speech',
            'file_path'        => 'knowledge/existing.pdf',
            'original_name'    => 'existing.pdf',
            'mime_type'        => 'application/pdf',
            'file_size'        => 1024,
            'status'           => 'failed',
            'processing_error' => 'Previous failure',
            'processed_at'     => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post("/en/admin/knowledge/{$document->id}/reprocess");

        $response->assertStatus(302)->assertSessionHas('success');

        $this->assertDatabaseHas('knowledge_documents', [
            'id'               => $document->id,
            'status'           => 'uploaded',
            'processing_error' => null,
            'processed_at'     => null,
        ]);

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
    }

    public function test_admin_can_fetch_live_document_statuses_for_batch_uploads(): void
    {
        $admin = Admin::query()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('secret'),
        ]);

        $uploaded = KnowledgeDocument::query()->create([
            'title'         => 'Uploaded Document',
            'category'      => 'Speech',
            'file_path'     => 'knowledge/uploaded.pdf',
            'original_name' => 'uploaded.pdf',
            'mime_type'     => 'application/pdf',
            'file_size'     => 2048,
            'status'        => 'uploaded',
        ]);

        $processed = KnowledgeDocument::query()->create([
            'title'         => 'Processed Deck',
            'category'      => 'Training',
            'file_path'     => 'knowledge/processed.pptx',
            'original_name' => 'processed.pptx',
            'mime_type'     => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'file_size'     => 4096,
            'status'        => 'processed',
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/en/admin/knowledge/statuses?ids=' . $uploaded->id . ',' . $processed->id, [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $uploaded->id)
            ->assertJsonPath('data.0.status', 'uploaded')
            ->assertJsonPath('data.1.id', $processed->id)
            ->assertJsonPath('data.1.status', 'processed');
    }
}

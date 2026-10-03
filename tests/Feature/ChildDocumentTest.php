<?php

namespace Tests\Feature;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChildDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->user = User::factory()->create(['ai_consent_accepted_at' => now()]);

        Storage::fake('public');
        Storage::fake('private');
        Bus::fake();
    }

    public function test_user_can_upload_child_document_with_uploaded_status(): void
    {
        $child = Child::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/children/{$child->id}/documents", [
                'title' => 'Speech Evaluation',
                'document_type' => 'assessment',
                'file' => UploadedFile::fake()->create('speech-eval.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('document_type', 'assessment');

        $this->assertDatabaseHas('child_documents', [
            'child_id' => $child->id,
            'user_id' => $this->user->id,
            'title' => 'Speech Evaluation',
            'category' => 'assessment',
            'status' => 'uploaded',
        ]);
        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, 1);
        $this->assertCount(0, Bus::dispatched(ProcessChildDocumentJob::class));
    }

    public function test_owned_child_route_cannot_delete_another_childs_document(): void
    {
        $ownChild = Child::factory()->create(['user_id' => $this->user->id]);
        $otherChild = Child::factory()->create();
        $document = ChildDocument::create([
            'child_id' => $otherChild->id,
            'user_id' => $otherChild->user_id,
            'original_name' => 'private-report.pdf',
            'file_path' => 'children/private-report.pdf',
            'status' => 'uploaded',
        ]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/children/{$ownChild->id}/documents/{$document->id}")
            ->assertNotFound();

        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_upload_without_ai_consent_keeps_file_without_starting_ai_extraction(): void
    {
        $this->user->update(['ai_consent_accepted_at' => null]);
        $child = Child::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/children/{$child->id}/documents", [
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'uploaded');

        Bus::assertNotDispatched(ProcessChildDocumentJob::class);
    }

    public function test_upload_uses_durable_queue_when_configured(): void
    {
        config(['queue.default' => 'database']);
        $child = Child::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/children/{$child->id}/documents", [
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'uploaded');

        Bus::assertDispatched(ProcessChildDocumentJob::class, 1);
        Bus::assertNotDispatchedAfterResponse(ProcessChildDocumentJob::class);
    }
}

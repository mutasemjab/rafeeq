<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChildDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Storage::fake('public');
    }

    public function test_user_can_upload_child_document_with_uploaded_status(): void
    {
        $child = Child::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/children/{$child->id}/documents", [
                'title'         => 'Speech Evaluation',
                'document_type' => 'assessment',
                'file'          => UploadedFile::fake()->create('speech-eval.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('document_type', 'assessment');

        $this->assertDatabaseHas('child_documents', [
            'child_id' => $child->id,
            'user_id'  => $this->user->id,
            'title'    => 'Speech Evaluation',
            'category' => 'assessment',
            'status'   => 'uploaded',
        ]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\ChatAttachment;
use App\Models\ChatAttachmentChunk;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\CaseDocumentContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDocumentContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_contains_only_current_conversation_and_owned_child_reference_data(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $otherUser = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $sibling = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $otherConversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $attachment = $this->attachment($user->id, $conversation, $child->id, 'A previously recorded assessment.');
        $this->attachment($user->id, $otherConversation, $child->id, 'Other conversation secret.');
        $this->attachment($otherUser->id, $conversation, $child->id, 'Other user secret.');
        $this->attachment($user->id, $conversation, $sibling->id, 'Sibling secret.');
        $deleted = $this->attachment($user->id, $conversation, $child->id, 'Deleted file secret.');
        $deleted->delete();
        $this->document($user->id, $child->id, 'Owned assessment');
        $this->document($user->id, $sibling->id, 'Sibling assessment secret');
        $this->document($otherUser->id, $child->id, 'Other user assessment secret');

        $context = (new CaseDocumentContextService())->build($user->id, $conversation, $child->id);

        $this->assertSame('user_supplied_reference_data', $context['trust']);
        $this->assertCount(1, $context['attachments']);
        $this->assertSame($attachment->id, $context['attachments'][0]['id']);
        $this->assertSame('A previously recorded assessment.', data_get($context, 'attachments.0.excerpts.0.content'));
        $this->assertCount(1, $context['child_documents']);
        $this->assertSame('Owned assessment', data_get($context, 'child_documents.0.excerpts.0.content'));
        $this->assertStringNotContainsString('secret', json_encode($context));
        $this->assertStringNotContainsString('file_path', json_encode($context));
    }

    public function test_unauthorized_conversation_or_child_override_has_no_document_context(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $sibling = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $this->attachment($user->id, $conversation, $child->id, 'Private assessment');
        $service = new CaseDocumentContextService();

        foreach ([$service->build(User::factory()->create()->id, $conversation, $child->id), $service->build($user->id, $conversation, $sibling->id)] as $context) {
            $this->assertSame([], $context['attachments']);
            $this->assertSame([], $context['child_documents']);
        }
    }

    public function test_documents_are_not_returned_after_ai_consent_is_withdrawn(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => null]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $this->attachment($user->id, $conversation, null, 'Private case text');

        $context = (new CaseDocumentContextService())->build($user->id, $conversation);

        $this->assertSame([], $context['attachments']);
        $this->assertSame([], $context['child_documents']);
    }

    public function test_malformed_chunk_owner_cannot_leak_through_an_owned_attachment(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $attachment = $this->attachment($user->id, $conversation, null, 'Owned text');
        ChatAttachmentChunk::create([
            'chat_attachment_id' => $attachment->id,
            'user_id' => User::factory()->create()->id,
            'conversation_id' => $conversation->id,
            'chunk_index' => 2,
            'content' => 'Mismatched chunk secret',
        ]);

        $context = (new CaseDocumentContextService())->build($user->id, $conversation);

        $this->assertCount(1, $context['attachments'][0]['excerpts']);
        $this->assertStringNotContainsString('secret', json_encode($context));
    }

    public function test_unprocessed_and_failed_files_are_visible_without_claiming_to_read_them(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $attachment = $this->attachment($user->id, $conversation, $child->id, 'Stale text');
        $attachment->update(['status' => 'failed', 'processing_error' => '/private/server/path']);
        $document = $this->document($user->id, $child->id, 'Stale assessment');
        $document->update(['status' => 'uploaded']);

        $context = (new CaseDocumentContextService())->build($user->id, $conversation);

        $this->assertFalse(data_get($context, 'attachments.0.content_available'));
        $this->assertSame('processing_failed', data_get($context, 'attachments.0.content_status'));
        $this->assertFalse(data_get($context, 'child_documents.0.content_available'));
        $this->assertSame('not_yet_readable', data_get($context, 'child_documents.0.content_status'));
        $this->assertStringNotContainsString('Stale', json_encode($context));
        $this->assertStringNotContainsString('/private/server/path', json_encode($context));
    }

    public function test_context_is_bounded_and_includes_report_conclusions(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        for ($i = 0; $i < 7; $i++) {
            $attachment = $this->attachment($user->id, $conversation, $child->id, str_repeat('ب', 3000));
            ChatAttachmentChunk::create([
                'chat_attachment_id' => $attachment->id,
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'child_id' => $child->id,
                'chunk_index' => 9,
                'content' => 'Report conclusion',
            ]);
            $this->document($user->id, $child->id, str_repeat('أ', 8000).'Final assessment');
        }

        $context = (new CaseDocumentContextService())->build($user->id, $conversation);

        $this->assertCount(5, $context['attachments']);
        $this->assertCount(5, $context['child_documents']);
        foreach (['attachments', 'child_documents'] as $group) {
            $chars = collect($context[$group])->flatMap(fn ($file) => $file['excerpts'])->sum(fn ($excerpt) => mb_strlen($excerpt['content']));
            $this->assertLessThanOrEqual(6000, $chars);
        }
        $this->assertSame('Report conclusion', data_get($context, 'attachments.0.excerpts.1.content'));
        $this->assertStringEndsWith('Final assessment', data_get($context, 'child_documents.0.excerpts.1.content'));
        $this->assertSame('context_budget_exhausted', data_get($context, 'child_documents.4.content_status'));
    }

    private function attachment(int $userId, Conversation $conversation, ?int $childId, string $text): ChatAttachment
    {
        $attachment = ChatAttachment::factory()->create([
            'user_id' => $userId,
            'conversation_id' => $conversation->id,
            'child_id' => $childId,
        ]);
        ChatAttachmentChunk::create([
            'chat_attachment_id' => $attachment->id,
            'user_id' => $userId,
            'conversation_id' => $conversation->id,
            'child_id' => $childId,
            'chunk_index' => 0,
            'content' => $text,
        ]);

        return $attachment;
    }

    private function document(int $userId, int $childId, string $text): ChildDocument
    {
        return ChildDocument::create([
            'user_id' => $userId,
            'child_id' => $childId,
            'file_path' => 'children/report.txt',
            'original_name' => 'report.txt',
            'title' => 'Assessment',
            'status' => 'processed',
            'metadata' => ['extracted_text' => $text],
        ]);
    }
}

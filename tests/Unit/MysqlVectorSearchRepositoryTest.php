<?php

namespace Tests\Unit;

use App\Models\ChatAttachment;
use App\Models\ChatAttachmentChunk;
use App\Models\Child;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Models\User;
use App\Repositories\MysqlVectorSearchRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MysqlVectorSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_search_excludes_deleted_files_and_inactive_or_mismatched_parents(): void
    {
        config(['ai.embedding_model' => 'test-embedding-model']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $child->delete();
        $attachments = [];
        foreach ([
            ['user_id' => $user->id],
            ['user_id' => $user->id, 'deleted_at' => now()],
            ['user_id' => $other->id],
            ['user_id' => $user->id, 'child_id' => $child->id],
        ] as $attributes) {
            $attachment = ChatAttachment::factory()->create(array_merge([
                'conversation_id' => $conversation->id, 'status' => 'processed',
            ], $attributes));
            $attachments[] = $attachment;
            ChatAttachmentChunk::create([
                'chat_attachment_id' => $attachment->id, 'conversation_id' => $conversation->id,
                'user_id' => $user->id, 'chunk_index' => 0, 'content' => 'Private facts',
                'embedding' => '[1.0,0.0]', 'embedding_dimensions' => 2,
                'metadata' => ['embedding_model' => 'test-embedding-model'],
            ]);
        }
        $repo = new MysqlVectorSearchRepository();
        $results = $repo->searchChatAttachments([1.0, 0.0], $user->id, $conversation->id, 5, 0.0);
        $this->assertSame([$attachments[0]->id], array_column($results, 'attachment_id'));
        $conversation->delete();
        $this->assertSame([], $repo->searchChatAttachments([1.0, 0.0], $user->id, $conversation->id, 5, 0.0));
    }

    public function test_large_search_path_retains_only_the_best_requested_results(): void
    {
        Config::set('ai.embedding_model', 'test-embedding-model');
        $document = KnowledgeDocument::create([
            'title' => 'Search Guide',
            'original_name' => 'search.txt',
            'file_path' => 'knowledge/search.txt',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        foreach ([[1.0, 0.0], [0.8, 0.2], [0.0, 1.0]] as $index => $embedding) {
            KnowledgeDocumentChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => 'Chunk '.$index,
                'embedding' => json_encode($embedding),
                'embedding_dimensions' => 2,
                'metadata' => ['embedding_model' => 'test-embedding-model'],
            ]);
        }

        $results = (new MysqlVectorSearchRepository())->searchKnowledge([1.0, 0.0], 2, 0.0);

        $this->assertCount(2, $results);
        $this->assertSame(0, $results[0]['chunk_index']);
        $this->assertSame(1, $results[1]['chunk_index']);
    }

    public function test_it_skips_vectors_from_a_different_model_or_dimension(): void
    {
        Config::set('ai.embedding_model', 'current-model');
        $document = KnowledgeDocument::create([
            'title' => 'Mixed Index',
            'original_name' => 'mixed.txt',
            'file_path' => 'knowledge/mixed.txt',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        foreach ([
            ['vector' => [1.0, 0.0], 'dimensions' => 2, 'model' => 'old-model'],
            ['vector' => [1.0, 0.0, 0.0], 'dimensions' => 3, 'model' => 'current-model'],
        ] as $index => $fixture) {
            KnowledgeDocumentChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => 'Chunk '.$index,
                'embedding' => json_encode($fixture['vector']),
                'embedding_dimensions' => $fixture['dimensions'],
                'metadata' => ['embedding_model' => $fixture['model']],
            ]);
        }

        $results = (new MysqlVectorSearchRepository())->searchKnowledge([1.0, 0.0], 5, 0.0);

        $this->assertSame([], $results);
    }

    public function test_multi_question_search_keeps_results_for_each_question(): void
    {
        Config::set('ai.embedding_model', 'test-embedding-model');
        $document = KnowledgeDocument::create([
            'title' => 'Multi-topic Guide',
            'original_name' => 'multi-topic.txt',
            'file_path' => 'knowledge/multi-topic.txt',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        foreach ([[1.0, 0.0], [0.0, 1.0]] as $index => $embedding) {
            KnowledgeDocumentChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => 'Topic '.$index,
                'embedding' => json_encode($embedding),
                'embedding_dimensions' => 2,
                'metadata' => ['embedding_model' => 'test-embedding-model'],
            ]);
        }

        $results = (new MysqlVectorSearchRepository())->searchKnowledgeMany(
            [[1.0, 0.0], [0.0, 1.0]],
            2,
            0.8
        );

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing([0, 1], array_column($results, 'chunk_index'));
    }
}

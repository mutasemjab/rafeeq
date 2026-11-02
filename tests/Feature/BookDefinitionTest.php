<?php

namespace Tests\Feature;

use App\Http\Resources\MessageResource;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Models\Message;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_definition_can_be_used_without_exposing_its_title_or_text(): void
    {
        $doc = KnowledgeDocument::create(['title' => 'اضطراب طيف التوحد', 'original_name' => 'test.txt',
            'status' => 'processed', 'file_path' => 'knowledge/test', 'is_approved' => true, 'show_attribution' => false]);
        KnowledgeDocumentChunk::create(['knowledge_document_id' => $doc->id, 'chunk_index' => 0, 'page_number' => 1,
            'content' => "اضطراب طيف التوحد\nالتعريف\n".str_repeat('هذا نص تجريبي لفحص اختيار المرجع الداخلي. ', 5)]);
        $search = app(KnowledgeSearchService::class);
        $sources = $search->findDefinition(['ماذا يعني التوحد؟', 'autism definition']);
        $this->assertSame($doc->id, $sources[0]['knowledge_document_id']);
        $this->assertSame(1, $sources[0]['page_number']);
        $this->assertSame([], $search->findDefinition(['ماذا يعني التوحد؟ وما العلاج؟']));
        $this->assertSame([], $search->findDefinition(['كيف أساعد ابني؟']));
        $source = ['source_type' => 'web', 'source_label' => 'WEB_SOURCE_1', 'url' => 'https://www.example.test/reference'];
        $answer = new Message(['role' => 'assistant', 'sources' => [...$sources, $source],
            'content' => 'إجابة [KB_SOURCE_1] [WEB_SOURCE_1] ([example.test](https://www.example.test/reference)) (example.test).']);
        $public = (new MessageResource($answer))->resolve();
        $this->assertSame('إجابة.', $public['content']);
        $this->assertSame([$source], $public['sources']);
        $this->getJson('/api/v1/knowledge')->assertJsonPath('meta.total', 0);
        $doc->update(['show_attribution' => true]);
        $book = (new MessageResource($answer))->resolve()['sources'][0];
        $this->assertSame($doc->title, $book['title']);
        $this->assertArrayNotHasKey('content', $book);
        $this->assertArrayNotHasKey('snippet', $book);
        $doc->update(['is_approved' => false]);
        $this->assertSame([], $search->findDefinition(['ماذا يعني التوحد؟']));
        $this->assertSame([$source], (new MessageResource($answer))->resolve()['sources']);
    }
}

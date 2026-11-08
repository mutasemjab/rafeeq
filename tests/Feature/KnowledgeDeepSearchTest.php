<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Repositories\MysqlVectorSearchRepository;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class KnowledgeDeepSearchTest extends TestCase
{
    use RefreshDatabase;

    private function document(array $attributes = []): KnowledgeDocument
    {
        return KnowledgeDocument::create(array_merge(['title' => 'Communication reference', 'original_name' => 'book.txt',
            'file_path' => 'knowledge/test', 'status' => 'processed', 'is_approved' => true, 'audience' => 'all_ages'], $attributes));
    }

    private function chunk(KnowledgeDocument $document, string $text, int $index = 0): KnowledgeDocumentChunk
    {
        return KnowledgeDocumentChunk::create(['knowledge_document_id' => $document->id, 'content' => $text,
            'chunk_index' => $index, 'page_number' => $index + 1]);
    }

    public function test_text_search_finds_an_unembedded_arabic_passage_and_its_continuation(): void
    {
        $document = $this->document();
        $anchor = $this->chunk($document, 'مَبَادِئ التَّوَاصُل تشمل تبادل الأدوار والاستماع.');
        $continuation = $this->chunk($document, 'ويستكمل الشرح في الصفحة التالية مع القيود والاستثناءات.', 1);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldNotReceive('embeddingMany');
        $results = (new KnowledgeSearchService($llm, new MysqlVectorSearchRepository()))
            ->searchDeeper([], ['مبادئ التواصل'], ['approved_only' => true]);
        $this->assertSame([$anchor->id, $continuation->id], array_column($results, 'chunk_id'));
        $this->assertSame([1, 2], array_column($results, 'page_number'));
        $this->assertSame(['KB_SOURCE_1', 'KB_SOURCE_2'], array_column($results, 'source_label'));
    }

    public function test_deeper_search_keeps_approval_status_deletion_and_age_boundaries(): void
    {
        $valid = $this->document();
        $validChunk = $this->chunk($valid, 'Communication turn taking and listening.');
        foreach ([['is_approved' => false], ['status' => 'failed'], ['deleted_at' => now()],
            ['audience' => null], ['audience' => 'children', 'age_min_months' => 0, 'age_max_months' => 216]] as $attributes) {
            $document = $this->document($attributes);
            if (isset($attributes['deleted_at'])) $document->forceFill(['deleted_at' => $attributes['deleted_at']])->save();
            $this->chunk($document, 'Communication turn taking and listening.');
        }
        $results = (new KnowledgeSearchService(Mockery::mock(LlmProviderInterface::class), new MysqlVectorSearchRepository()))
            ->searchDeeper([], ['communication turn taking'], ['approved_only' => true, 'require_age_scope' => true, 'age_months' => 360]);
        $this->assertSame([$validChunk->id], array_column($results, 'chunk_id'));
    }

    public function test_matching_book_titles_do_not_hide_the_answer_on_a_later_page(): void
    {
        $document = $this->document(['title' => 'Turn taking communication reference']);
        for ($index = 0; $index < 50; $index++) {
            $this->chunk($document, 'Communication background and unrelated introductory material.', $index);
        }
        $answer = $this->chunk($document, 'Turn taking communication involves listening and alternating contributions.', 50);
        $results = (new KnowledgeSearchService(Mockery::mock(LlmProviderInterface::class), new MysqlVectorSearchRepository()))
            ->searchDeeper([], ['turn taking communication'], ['approved_only' => true]);
        $this->assertSame($answer->id, $results[0]['chunk_id']);
    }

    public function test_multilingual_matches_and_stable_ties_survive_multiple_text_batches(): void
    {
        config(['ai.max_knowledge_chunks' => 4]);
        $document = $this->document(['title' => 'Reference']);
        $early = [];
        for ($index = 0; $index < 205; $index++) {
            $chunk = $this->chunk($document, 'التواصل فقط. Communication only.', $index);
            if ($index < 2) $early[] = $chunk->id;
        }
        $arabic = $this->chunk($document, 'مَبَادِئ التَّوَاصُل الفَعَّال.', 205);
        $english = $this->chunk($document, 'Effective communication principles.', 206);
        $results = (new KnowledgeSearchService(Mockery::mock(LlmProviderInterface::class), new MysqlVectorSearchRepository()))
            ->searchDeeper([], ['مبادئ التواصل الفعال', 'effective communication principles'], ['approved_only' => true]);
        $this->assertSame([$arabic->id, $english->id, ...$early], array_slice(array_column($results, 'chunk_id'), 0, 4));
        $this->assertSame('مَبَادِئ التَّوَاصُل الفَعَّال.', $results[0]['content']);
        $this->assertSame('Effective communication principles.', $results[1]['content']);
    }
}

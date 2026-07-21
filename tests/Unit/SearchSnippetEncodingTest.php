<?php

namespace Tests\Unit;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\KnowledgeSearchService;
use Mockery;
use Tests\TestCase;

class SearchSnippetEncodingTest extends TestCase
{
    public function test_knowledge_snippets_do_not_split_arabic_characters(): void
    {
        $content = str_repeat(' ا', 210);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embedding')->once()->with('ما هو التوحد')->andReturn([1.0]);

        $repository = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repository->shouldReceive('searchKnowledge')
            ->once()
            ->andReturn([['content' => $content]]);

        $results = (new KnowledgeSearchService($llm, $repository))->search('ما هو التوحد');

        $this->assertTrue(mb_check_encoding($results[0]['snippet'], 'UTF-8'));
        $this->assertIsString(json_encode($results, JSON_THROW_ON_ERROR));
    }

    public function test_attachment_snippets_do_not_split_arabic_characters(): void
    {
        $content = str_repeat(' ا', 210);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embedding')->once()->with('ما هو التوحد')->andReturn([1.0]);

        $repository = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repository->shouldReceive('searchChatAttachments')
            ->once()
            ->andReturn([['content' => $content]]);

        $results = (new ChatAttachmentSearchService($llm, $repository))->search(
            1,
            38,
            'ما هو التوحد'
        );

        $this->assertTrue(mb_check_encoding($results[0]['snippet'], 'UTF-8'));
        $this->assertIsString(json_encode($results, JSON_THROW_ON_ERROR));
    }
}

<?php

namespace Tests\Unit;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\ChatAttachmentSearchService;
use Mockery;
use Tests\TestCase;

class ChatAttachmentSearchServiceTest extends TestCase
{
    public function test_attachment_sources_preserve_owned_id_without_a_bearer_or_public_url_in_model_context(): void
    {
        $repo = Mockery::mock(VectorSearchRepositoryInterface::class);
        $repo->shouldReceive('searchChatAttachments')->once()->with([0.1, 0.2], 4, 9, 3, Mockery::type('float'))
            ->andReturn([[
                'attachment_id' => 17, 'original_name' => 'Private assessment.pdf',
                'content' => 'Reported facts about the child.', 'url' => 'https://example.com/storage/private-report.pdf',
            ]]);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldNotReceive('embedding');

        $result = (new ChatAttachmentSearchService($llm, $repo))->searchWithEmbeddings(4, 9, [[0.1, 0.2]], 3)[0];

        $this->assertSame(17, $result['attachment_id']);
        $this->assertSame('chat_attachment', $result['source_type']);
        $this->assertSame('Private assessment.pdf', $result['title']);
        $this->assertArrayNotHasKey('url', $result);
    }
}

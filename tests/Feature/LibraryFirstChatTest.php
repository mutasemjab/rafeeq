<?php

namespace Tests\Feature;

use App\Exceptions\ChatServiceUnavailableException;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\ChildChatService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\DomainGuardService;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class LibraryFirstChatTest extends TestCase
{
    use RefreshDatabase;

    private $llm;
    private $search;
    private array $embeddedQueryBatches = [];
    private User $user;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
        config(['ai.openai_web_search_enabled' => true, 'ai.web_search_enabled' => false,
            'ai.require_retrieved_evidence' => true, 'ai.skip_empty_retrieval_embeddings' => false]);
        $this->user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $this->conversation = Conversation::factory()->create(['user_id' => $this->user->id]);
        $this->llm = Mockery::mock(LlmProviderInterface::class);
        $this->llm->shouldReceive('embeddingMany')->andReturnUsing(function ($queries) {
            $this->embeddedQueryBatches[] = $queries;
            return array_map(fn ($query) => [(float) strlen($query), 1.0], $queries);
        });
        $this->app->instance(LlmProviderInterface::class, $this->llm);
        $this->search = Mockery::mock(KnowledgeSearchService::class);
        $this->app->instance(KnowledgeSearchService::class, $this->search);
        $this->mock(ChatAttachmentSearchService::class)->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $this->mock(WebSearchServiceInterface::class)->shouldNotReceive('search');
        $this->mock(DomainGuardService::class)->shouldReceive('evaluate')->once()->andReturn([
            'allowed' => true, 'confidence' => 1.0, 'category' => 'communication', 'model' => 'test',
            'search_queries' => [], 'general_education' => false,
        ]);
    }

    private function plan(array $overrides = []): void
    {
        $this->mock(ChatTurnPlannerService::class)->shouldReceive('plan')->once()->andReturn(array_merge([
            'action' => 'answer', 'domain' => 'communication', 'case_specific' => false,
            'risk_level' => 'low', 'missing_fields' => [], 'search_queries' => [],
            'follow_up_needed' => false, 'evidence_required' => true, 'web_search_needed' => false,
        ], $overrides));
    }

    private function source(string $content = 'Communication requires taking turns and listening to the other person.', float $score = 0.36): array
    {
        return ['chunk_id' => 1, 'knowledge_document_id' => 1, 'source_type' => 'knowledge_base',
            'source_label' => 'KB_SOURCE_1', 'content' => $content, 'retrieval_score' => $score];
    }

    private function coverage(bool $sufficient, array $queries = []): array
    {
        return ['sufficient' => $sufficient,
            'supporting_quotes' => $sufficient ? [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 1]] : [],
            'missing_information' => $sufficient ? [] : ['How to take turns is not explained.'], 'search_queries' => $queries];
    }

    private function review(): void
    {
        $this->llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(['action' => 'approve', 'follow_up' => ['question' => null], '_provider_web_search_verified' => true]);
    }

    private function ask()
    {
        return app(ChildChatService::class)->ask($this->conversation, 'Explain communication and turn taking.', $this->user->id, null, 'en');
    }

    public function test_low_score_and_high_risk_do_not_send_a_book_supported_answer_to_web(): void
    {
        $this->plan(['risk_level' => 'high', 'web_search_needed' => true]);
        $unrelated = array_merge($this->source('An unrelated recommendation that should not reach the answer.', 0.99),
            ['source_label' => 'KB_SOURCE_2', 'chunk_id' => 2]);
        $this->search->shouldReceive('searchForCase')->once()->andReturn([$this->source(), $unrelated]);
        $this->search->shouldNotReceive('searchDeeper');
        $this->llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_knowledge_coverage')
            ->andReturn($this->coverage(true));
        $this->review();
        $this->llm->shouldReceive('answer')->once()->withArgs(function ($messages, $options) {
            $this->assertFalse($options['web_search']);
            $this->assertFalse($options['web_search_required']);
            $this->assertStringContainsString('taking turns and listening', $messages[1]['content']);
            $this->assertStringNotContainsString('An unrelated recommendation', $messages[1]['content']);
            return true;
        })->andReturn(['content' => 'Take turns and listen to the other person. [KB_SOURCE_1]', 'sources' => []]);
        $reply = $this->ask();
        $this->assertTrue($reply->metadata['evidence']['internal_answer_sufficient']);
        $this->assertFalse($reply->metadata['evidence']['hosted_web_search_requested']);
        $this->assertSame(1, $reply->metadata['evidence']['internal_search_passes']);
        $this->assertSame('internal', $reply->metadata['turn_plan']['knowledge_grounding']['mode']);
        Http::assertNothingSent();
    }

    public function test_a_second_internal_search_finds_the_answer_before_web_is_allowed(): void
    {
        $this->plan();
        $this->search->shouldReceive('searchForCase')->once()->andReturn([$this->source('Communication is a broad topic.', 0.95)]);
        $this->search->shouldReceive('searchDeeper')->once()->withArgs(function ($vectors, $queries) {
            $this->assertContains('turn taking listening', $queries);
            $this->assertSame(count($queries), count($vectors));
            $this->assertSame(array_map(fn ($query) => [(float) strlen($query), 1.0], $queries), $vectors);
            return true;
        })->andReturn([$this->source()]);
        $this->llm->shouldReceive('chatJson')->twice()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_knowledge_coverage')
            ->andReturn($this->coverage(false, ['turn taking listening']), $this->coverage(true));
        $this->review();
        $this->llm->shouldReceive('answer')->once()->withArgs(fn ($m, $o) => $o['web_search'] === false)
            ->andReturn(['content' => 'Take turns and listen. [KB_SOURCE_1]', 'sources' => []]);
        $reply = $this->ask();
        $this->assertSame(2, $reply->metadata['evidence']['internal_search_passes']);
        $this->assertTrue($reply->metadata['evidence']['internal_answer_sufficient']);
        $this->assertCount(2, $this->embeddedQueryBatches);
        $this->assertSame(['turn taking listening'], $this->embeddedQueryBatches[1]);
    }

    public function test_web_is_used_only_after_both_internal_searches_have_no_answer(): void
    {
        $this->plan();
        $secondSearchCompleted = false;
        $this->search->shouldReceive('searchForCase')->once()->andReturn([]);
        $this->search->shouldReceive('searchDeeper')->once()->andReturnUsing(function () use (&$secondSearchCompleted) {
            $secondSearchCompleted = true;
            return [];
        });
        $this->review();
        $this->llm->shouldReceive('answer')->once()->withArgs(function ($messages, $options) use (&$secondSearchCompleted) {
            $this->assertTrue($secondSearchCompleted);
            $this->assertTrue($options['web_search']);
            $this->assertTrue($options['web_search_required']);
            $this->assertStringContainsString('web_gap_fill', $messages[1]['content']);
            return true;
        })->andReturn(['content' => 'A web-supported explanation. [WEB_SOURCE_1]', 'used_web_search' => true,
            'sources' => [['url' => 'https://example.org/communication', 'snippet' => 'A web-supported explanation.']]]);
        $reply = $this->ask();
        $this->assertSame('internal_answer_gap_after_search', $reply->metadata['evidence']['web_fallback_reason']);
        $this->assertSame(2, $reply->metadata['evidence']['internal_search_passes']);
        $this->assertTrue($reply->metadata['evidence']['used_web_search']);
    }

    public function test_coverage_outage_is_not_treated_as_permission_to_bypass_the_books(): void
    {
        $this->plan();
        $this->search->shouldReceive('searchForCase')->once()->andReturn([$this->source()]);
        $this->llm->shouldReceive('chatJson')->once()->andThrow(new \RuntimeException('Coverage unavailable'));
        $this->llm->shouldNotReceive('answer');
        $this->expectException(ChatServiceUnavailableException::class);
        $this->ask();
    }
}

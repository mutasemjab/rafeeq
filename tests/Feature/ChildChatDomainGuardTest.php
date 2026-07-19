<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ChildChatService;
use App\Services\AI\ChildContextService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\DomainGuardService;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ChildChatDomainGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrelated_questions_never_reach_retrieval_or_answer_generation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'message_count' => 0,
        ]);
        $decision = [
            'allowed' => false,
            'confidence' => 0.99,
            'category' => 'coding',
            'reason' => 'Outside Rafiq scope.',
            'model' => 'guard-model',
        ];
        $refusal = 'I can only help with Rafiq topics.';

        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chat')->never();
        $llm->shouldReceive('embedding')->never();
        $llm->shouldReceive('embeddingMany')->never();
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->never();
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('search')->never();
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('search')->never();
        $webSearch = Mockery::mock(WebSearchServiceInterface::class);
        $webSearch->shouldReceive('search')->never();
        $guard = Mockery::mock(DomainGuardService::class);
        $guard->shouldReceive('evaluate')->once()->andReturn($decision);
        $guard->shouldReceive('refusal')->once()->andReturn($refusal);

        $service = new ChildChatService(
            $llm,
            $childContext,
            $knowledgeSearch,
            $attachmentSearch,
            $webSearch,
            $guard
        );
        $reply = $service->ask(
            $conversation,
            'Write a Python web scraper.',
            $user->id,
            null,
            'en'
        );

        $this->assertSame($refusal, $reply->content);
        $this->assertSame([], $reply->sources);
        $this->assertSame($decision, $reply->metadata['domain_guard']);
        $this->assertSame(['out_of_scope'], $reply->safety_flags);
        $this->assertDatabaseCount('messages', 2);
        $this->assertSame(1, $conversation->fresh()->message_count);
    }

    public function test_related_questions_continue_to_retrieval_and_the_answer_model(): void
    {
        Config::set('ai.default_medical_sources', []);
        Config::set('ai.web_search_enabled', false);
        Config::set('ai.chat_model', 'smart-answer-model');
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $decision = [
            'allowed' => true,
            'confidence' => 0.98,
            'category' => 'speech_language',
            'reason' => 'Within Rafiq scope.',
            'model' => 'guard-model',
        ];

        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chat')->once()->andReturn('Use a short daily articulation activity.');
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->once()->andReturn([
            'profile' => null,
            'memories' => [],
            'summary' => null,
        ]);
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('search')->once()->andReturn([]);
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('search')->once()->andReturn([]);
        $webSearch = Mockery::mock(WebSearchServiceInterface::class);
        $webSearch->shouldReceive('search')->never();
        $guard = Mockery::mock(DomainGuardService::class);
        $guard->shouldReceive('evaluate')->once()->andReturn($decision);

        $service = new ChildChatService(
            $llm,
            $childContext,
            $knowledgeSearch,
            $attachmentSearch,
            $webSearch,
            $guard
        );
        $reply = $service->ask(
            $conversation,
            'How can my child practice the R sound?',
            $user->id
        );

        $this->assertSame('Use a short daily articulation activity.', $reply->content);
        $this->assertSame('smart-answer-model', $reply->model_name);
        $this->assertSame($decision, $reply->metadata['domain_guard']);
    }
}

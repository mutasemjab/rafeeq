<?php

namespace Tests\Feature;

use App\Exceptions\ChatServiceUnavailableException;
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
        $llm->shouldReceive('embeddingMany')
            ->once()
            ->with(['How can my child practice the R sound?'])
            ->andReturn([[1.0, 0.0]]);
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->once()->andReturn([
            'profile' => null,
            'memories' => [],
            'summary' => null,
        ]);
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('searchWithEmbeddings')
            ->once()
            ->with([[1.0, 0.0]])
            ->andReturn([]);
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('searchWithEmbeddings')
            ->once()
            ->with($user->id, $conversation->id, [[1.0, 0.0]])
            ->andReturn([]);
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
        $this->assertSame('answer', $reply->metadata['response_type']);
    }

    public function test_multiple_questions_are_embedded_in_one_batch_and_retrieved_separately(): void
    {
        Config::set('ai.default_medical_sources', []);
        Config::set('ai.web_search_enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $decision = [
            'allowed' => true,
            'confidence' => 0.99,
            'category' => 'speech_language',
            'reason' => 'Within Rafiq scope.',
            'model' => 'guard-model',
        ];
        $questions = [
            'ما مراحل البلع؟',
            'ما مؤشرات تأخر اللغة؟',
        ];
        $embeddings = [
            [1.0, 0.0],
            [0.0, 1.0],
        ];

        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embeddingMany')
            ->once()
            ->with($questions)
            ->andReturn($embeddings);
        $llm->shouldReceive('chat')->once()->andReturn('إجابة للسؤالين.');
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->once()->andReturn([
            'profile' => null,
            'memories' => [],
            'summary' => null,
        ]);
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('searchWithEmbeddings')
            ->once()
            ->with($embeddings)
            ->andReturn([]);
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('searchWithEmbeddings')
            ->once()
            ->with($user->id, $conversation->id, $embeddings)
            ->andReturn([]);
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
            implode('', $questions),
            $user->id,
            null,
            'ar'
        );

        $this->assertSame('إجابة للسؤالين.', $reply->content);
        $this->assertSame(2, $reply->metadata['retrieval_query_count']);
    }

    public function test_answer_provider_failure_returns_service_unavailable_without_fake_message(): void
    {
        Config::set('ai.default_medical_sources', []);
        Config::set('ai.web_search_enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'message_count' => 0,
        ]);
        $decision = [
            'allowed' => true,
            'confidence' => 0.99,
            'category' => 'speech_language',
            'reason' => 'Within Rafiq scope.',
            'model' => 'guard-model',
        ];

        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embeddingMany')->once()->andReturn([[1.0, 0.0]]);
        $llm->shouldReceive('chat')
            ->once()
            ->andThrow(new \RuntimeException('Provider timeout.'));
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->once()->andReturn([
            'profile' => null,
            'memories' => [],
            'summary' => null,
        ]);
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
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

        try {
            $service->ask(
                $conversation,
                'How can I support language development?',
                $user->id,
                null,
                'en'
            );
            $this->fail('Expected a service-unavailable exception.');
        } catch (ChatServiceUnavailableException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringContainsString('could not be completed', $exception->getMessage());
            $this->assertSame('answer_generation', $exception->stage());
            $this->assertSame('AI_ANSWER_UNAVAILABLE', $exception->errorCode());
        }

        $this->assertDatabaseCount('messages', 0);
        $this->assertSame(0, $conversation->fresh()->message_count);
    }

    public function test_arabic_is_detected_and_invalid_source_text_is_bounded_and_normalized(): void
    {
        Config::set('ai.default_medical_sources', []);
        Config::set('ai.web_search_enabled', false);
        Config::set('ai.max_source_context_chars', 200);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        $decision = [
            'allowed' => true,
            'confidence' => 0.99,
            'category' => 'speech_language',
            'reason' => 'Within Rafiq scope.',
            'search_queries' => ['preschool receptive and expressive language assessment'],
            'model' => 'guard-model',
        ];
        $invalidContent = 'مصدر '.str_repeat('x', 500)."\xC3\x28";
        $capturedMessages = [];

        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('embeddingMany')
            ->once()
            ->with($decision['search_queries'])
            ->andReturn([[1.0, 0.0]]);
        $llm->shouldReceive('chat')
            ->once()
            ->andReturnUsing(function (array $messages) use (&$capturedMessages): string {
                $capturedMessages = $messages;

                return 'إجابة عربية.';
            });
        $childContext = Mockery::mock(ChildContextService::class);
        $childContext->shouldReceive('build')->once()->andReturn([
            'profile' => null,
            'memories' => [],
            'summary' => null,
        ]);
        $knowledgeSearch = Mockery::mock(KnowledgeSearchService::class);
        $knowledgeSearch->shouldReceive('searchWithEmbeddings')->once()->andReturn([[
            'source_label' => 'KB_SOURCE_1',
            'source_type' => 'knowledge_base',
            'title' => 'Language assessment',
            'content' => $invalidContent,
            'snippet' => 'Assessment source.',
        ]]);
        $attachmentSearch = Mockery::mock(ChatAttachmentSearchService::class);
        $attachmentSearch->shouldReceive('searchWithEmbeddings')->once()->andReturn([]);
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
            'كيف أقيّم اللغة الاستقبالية والتعبيرية؟',
            $user->id,
            null,
            'en'
        );

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('Respond in Arabic.', $systemPrompt);
        $this->assertStringNotContainsString(str_repeat('x', 250), $systemPrompt);
        $this->assertTrue(mb_check_encoding($systemPrompt, 'UTF-8'));
        $this->assertSame('إجابة عربية.', $reply->content);
        $this->assertNotFalse(json_encode($reply->sources));
    }
}

<?php

namespace App\Services\AI;

use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateChildMemoryJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChildChatService
{
    public function __construct(
        private LlmProviderInterface $llm,
        private ChildContextService $childContext,
        private KnowledgeSearchService $knowledgeSearch,
        private ChatAttachmentSearchService $attachmentSearch,
        private WebSearchServiceInterface $webSearch,
        private DomainGuardService $domainGuard,
    ) {
    }

    public function ask(
        Conversation $conversation,
        string $userMessage,
        int $userId,
        ?int $childId = null,
        ?string $language = 'en',
    ): Message {
        Log::info('[Chat] ── START ──────────────────────────────', [
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'child_id' => $childId,
            'language' => $language,
            'message_length' => mb_strlen($userMessage),
        ]);

        // 1. Persist user message
        $userMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);
        Log::info('[Chat] Step 1: User message saved', ['message_id' => $userMsg->id]);

        // 2. Enforce Rafiq's subject boundary before retrieval or answer generation.
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->take(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse()
            ->values();
        $guardHistory = $recentMessages
            ->reject(fn (Message $message): bool => $message->id === $userMsg->id)
            ->map(fn (Message $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
        $domainDecision = $this->domainGuard->evaluate($userMessage, $guardHistory);

        Log::info('[Chat] Step 2: Domain guard evaluated', [
            'allowed' => $domainDecision['allowed'],
            'confidence' => $domainDecision['confidence'],
            'category' => $domainDecision['category'],
            'model' => $domainDecision['model'],
        ]);

        if (($domainDecision['category'] ?? null) === 'guard_error') {
            throw $this->serviceUnavailableException(
                $userMsg,
                $language,
                'domain_guard',
                new \RuntimeException((string) ($domainDecision['reason'] ?? 'Domain guard failed.'))
            );
        }

        if (! $domainDecision['allowed']) {
            return $this->persistDomainRefusal(
                $conversation,
                $userMessage,
                $language,
                $domainDecision
            );
        }

        // 3. Build child context
        try {
            $childCtx = $this->childContext->build($childId, $userId);
            Log::info('[Chat] Step 3: Child context built', [
                'has_profile' => ! empty($childCtx['profile']),
                'memory_count' => count($childCtx['memories'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 3 FAILED: child context', ['error' => $e->getMessage()]);
            $childCtx = ['profile' => null, 'memories' => [], 'summary' => null];
        }

        try {
            $retrievalQueries = $this->retrievalQueries(
                $userMessage,
                $domainDecision['search_queries'] ?? []
            );
            $queryEmbeddings = $this->llm->embeddingMany($retrievalQueries);

            if (count($queryEmbeddings) !== count($retrievalQueries)) {
                throw new \RuntimeException('The embedding provider returned an unexpected number of vectors.');
            }

            Log::info('[Chat] Retrieval queries embedded', [
                'query_count' => count($retrievalQueries),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Retrieval embedding FAILED', ['error' => $e->getMessage()]);
            throw $this->serviceUnavailableException($userMsg, $language, 'embedding', $e);
        }

        // 4. Search chat attachments (user + conversation scoped)
        try {
            $attachmentSources = $this->attachmentSearch->searchWithEmbeddings(
                $userId,
                (int) $conversation->id,
                $queryEmbeddings
            );
            Log::info('[Chat] Step 4: Chat attachment search', [
                'chunks_found' => count($attachmentSources),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 4 FAILED: attachment search', ['error' => $e->getMessage()]);
            throw $this->serviceUnavailableException($userMsg, $language, 'attachment_search', $e);
        }

        // 5. Search knowledge base
        try {
            $knowledgeSources = $this->knowledgeSearch->searchWithEmbeddings($queryEmbeddings);
            Log::info('[Chat] Step 5: Knowledge base search', [
                'chunks_found' => count($knowledgeSources),
            ]);

            foreach ($knowledgeSources as $i => $chunk) {
                Log::info('[Chat] Step 5: KB chunk #'.($i + 1), [
                    'source_label' => $chunk['source_label'] ?? null,
                    'document_id' => $chunk['knowledge_document_id'] ?? null,
                    'similarity' => round($chunk['similarity'] ?? 0, 4),
                    'content_preview' => mb_substr($chunk['content'] ?? '', 0, 200),
                ]);
            }

            if (empty($knowledgeSources)) {
                Log::warning('[Chat] Step 5: No knowledge chunks found — AI will answer without KB context');
            }
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 5 FAILED: knowledge search', ['error' => $e->getMessage()]);
            throw $this->serviceUnavailableException($userMsg, $language, 'knowledge_search', $e);
        }

        // 6. Search web fallback when enabled.
        $webSources = $this->searchWebSources($userMessage);

        // 7. Always include public medical/wellness references for App Review citation requirements.
        $medicalSources = $this->defaultMedicalSources();

        // 8. Merge sources
        $allSources = array_values(array_merge(
            $attachmentSources,
            $knowledgeSources,
            $webSources,
            $medicalSources
        ));
        Log::info('[Chat] Step 8: Total sources merged', [
            'total' => count($allSources),
            'attachment_sources' => count($attachmentSources),
            'knowledge_sources' => count($knowledgeSources),
            'web_sources' => count($webSources),
            'medical_sources' => count($medicalSources),
        ]);

        // 9. Build context string
        $sourceContext = $this->buildSourceContext($allSources);

        Log::info('[Chat] Step 9: History loaded', ['message_count' => $recentMessages->count()]);

        // 10. Build LLM messages array
        $systemPrompt = config('ai.system_prompt', '');

        if (! empty($childCtx['profile'])) {
            $systemPrompt .= "\n\nChild Profile:\n".json_encode($childCtx['profile'], JSON_UNESCAPED_UNICODE);
        }
        if (! empty($childCtx['memories'])) {
            $systemPrompt .= "\n\nChild Memories:\n".json_encode($childCtx['memories'], JSON_UNESCAPED_UNICODE);
        }
        if ($conversation->summary) {
            $systemPrompt .= "\n\nConversation Summary:\n".$conversation->summary;
        }
        if ($sourceContext) {
            $systemPrompt .= "\n\nRelevant Context:".$sourceContext;
        }
        if ($language === 'ar') {
            $systemPrompt .= "\n\nRespond in Arabic.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($recentMessages as $msg) {
            if ($msg->id === $userMsg->id) {
                continue;
            }
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        Log::info('[Chat] Step 10: LLM payload ready', [
            'total_messages' => count($messages),
            'system_prompt_len' => strlen($systemPrompt),
            'has_context' => ! empty($sourceContext),
        ]);

        // 11. Call LLM
        try {
            $reply = $this->llm->chat($messages);
            Log::info('[Chat] Step 11: LLM replied', ['reply_length' => strlen($reply)]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 11 FAILED: LLM call', ['error' => $e->getMessage()]);
            throw $this->serviceUnavailableException($userMsg, $language, 'answer_generation', $e);
        }

        // 12. Persist assistant message
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
            'sources' => $allSources,
            'metadata' => [
                'response_type' => 'answer',
                'domain_guard' => $domainDecision,
                'retrieval_query_count' => count($retrievalQueries),
                'knowledge_source_count' => count($knowledgeSources),
                'attachment_source_count' => count($attachmentSources),
            ],
            'model_name' => config('ai.chat_model'),
        ]);

        Log::info('[Chat] Step 12: Assistant message saved', ['message_id' => $assistantMsg->id]);

        // 13. Increment count and dispatch background jobs
        $conversation->increment('message_count');
        $conversation->touch();
        $count = $conversation->fresh()->message_count ?? 0;

        if ($childId && $count > 0 && $count % 5 === 0) {
            UpdateChildMemoryJob::dispatch($conversation->id, $childId);
            Log::info('[Chat] Dispatched UpdateChildMemoryJob', ['count' => $count]);
        }
        if ($count > 0 && $count % 10 === 0) {
            SummarizeConversationJob::dispatch($conversation->id);
            Log::info('[Chat] Dispatched SummarizeConversationJob', ['count' => $count]);
        }

        Log::info('[Chat] ── END ── reply sent', ['conversation_id' => $conversation->id]);

        return $assistantMsg;
    }

    private function persistDomainRefusal(
        Conversation $conversation,
        string $userMessage,
        ?string $language,
        array $domainDecision
    ): Message {
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $this->domainGuard->refusal($language, $userMessage),
            'sources' => [],
            'metadata' => [
                'response_type' => 'domain_refusal',
                'domain_guard' => $domainDecision,
            ],
            'safety_flags' => ['out_of_scope'],
        ]);

        $conversation->increment('message_count');
        $conversation->touch();

        Log::info('[Chat] ── END ── unrelated question refused', [
            'conversation_id' => $conversation->id,
            'assistant_message_id' => $assistantMsg->id,
            'category' => $domainDecision['category'] ?? 'uncertain',
        ]);

        return $assistantMsg;
    }

    private function retrievalQueries(string $message, array $suggestedQueries = []): array
    {
        $maxQuestions = max(1, (int) config('ai.max_questions_per_message', 4));
        $suggestedQueries = array_values(array_filter(
            array_map(
                fn ($query): string => is_string($query) ? trim($query) : '',
                $suggestedQueries
            ),
            fn (string $query): bool => $query !== ''
        ));
        if ($suggestedQueries !== []) {
            return array_slice($suggestedQueries, 0, $maxQuestions);
        }

        $normalized = preg_replace('/([?؟])(?=\p{L})/u', '$1 ', trim($message)) ?? trim($message);
        $parts = preg_split('/(?<=[?؟])\s+/u', $normalized, $maxQuestions) ?: [];
        $parts = array_values(array_filter(
            array_map('trim', $parts),
            fn (string $part): bool => $part !== ''
        ));

        return $parts !== [] ? $parts : [trim($message)];
    }

    private function serviceUnavailableException(
        Message $userMessage,
        ?string $language,
        string $stage,
        \Throwable $previous
    ): HttpException {
        $userMessage->delete();

        $isArabic = $language === 'ar'
            || preg_match('/\p{Arabic}/u', (string) $userMessage->content) === 1;
        $message = $isArabic
            ? 'تعذّر إكمال الإجابة الآن. لم يتم حفظ رد غير مكتمل؛ يرجى المحاولة مرة أخرى.'
            : 'The answer could not be completed. No incomplete response was saved; please try again.';

        Log::warning('[Chat] Request failed explicitly', [
            'stage' => $stage,
            'conversation_id' => $userMessage->conversation_id,
        ]);

        return new HttpException(503, $message, $previous);
    }

    private function searchWebSources(string $userMessage): array
    {
        if (! config('ai.web_search_enabled', false)) {
            return [];
        }

        try {
            $results = $this->webSearch->search($userMessage, [
                'count' => 3,
                'safesearch' => 'strict',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Chat] Web search failed', ['error' => $e->getMessage()]);

            return [];
        }

        return collect($results)
            ->filter(fn ($source) => ! empty($source['url']))
            ->take(3)
            ->values()
            ->map(function (array $source, int $index): array {
                return [
                    'source_label' => 'WEB_SOURCE_'.($index + 1),
                    'source_type' => 'web',
                    'title' => $source['title'] ?? 'Web source',
                    'url' => $source['url'] ?? '',
                    'snippet' => $source['snippet'] ?? '',
                    'content' => $source['snippet'] ?? '',
                ];
            })
            ->all();
    }

    private function defaultMedicalSources(): array
    {
        $configuredSources = config('ai.default_medical_sources', []);

        if (! is_array($configuredSources)) {
            return [];
        }

        $sources = [];

        foreach ($configuredSources as $index => $source) {
            if (! is_array($source)) {
                continue;
            }

            $label = $source['source_label'] ?? 'MED_SOURCE_'.($index + 1);
            $title = $source['title'] ?? 'Medical reference';
            $url = $source['url'] ?? '';
            $snippet = $source['snippet'] ?? '';

            $sources[] = [
                'source_label' => $label,
                'source_type' => $source['source_type'] ?? 'medical_reference',
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet,
                'content' => trim($snippet.($url ? "\nURL: {$url}" : '')),
            ];
        }

        return $sources;
    }

    private function buildSourceContext(array $sources): string
    {
        $sourceContext = '';

        foreach ($sources as $source) {
            $label = $source['source_label'] ?? $source['label'] ?? 'SOURCE';
            $lines = [];

            foreach (['title' => 'Title', 'url' => 'URL', 'snippet' => 'Summary'] as $key => $labelText) {
                if (! empty($source[$key])) {
                    $lines[] = $labelText.': '.$source[$key];
                }
            }

            if (! empty($source['content']) && ! in_array($source['content'], $lines, true)) {
                $lines[] = 'Content: '.$source['content'];
            }

            if (empty($lines)) {
                continue;
            }

            $sourceContext .= "\n\n[{$label}]\n".implode("\n", $lines);
        }

        return $sourceContext;
    }
}

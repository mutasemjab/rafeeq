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

class ChildChatService
{
    public function __construct(
        private LlmProviderInterface $llm,
        private ChildContextService $childContext,
        private KnowledgeSearchService $knowledgeSearch,
        private ChatAttachmentSearchService $attachmentSearch,
        private WebSearchServiceInterface $webSearch,
    ) {}

    public function ask(
        Conversation $conversation,
        string $userMessage,
        int $userId,
        ?int $childId = null,
        ?string $language = 'en',
    ): Message {
        Log::info('[Chat] ── START ──────────────────────────────', [
            'conversation_id' => $conversation->id,
            'user_id'         => $userId,
            'child_id'        => $childId,
            'language'        => $language,
            'message_length'  => mb_strlen($userMessage),
        ]);

        // 1. Persist user message
        $userMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);
        Log::info('[Chat] Step 1: User message saved', ['message_id' => $userMsg->id]);

        // 2. Build child context
        try {
            $childCtx = $this->childContext->build($childId, $userId);
            Log::info('[Chat] Step 2: Child context built', [
                'has_profile'  => !empty($childCtx['profile']),
                'memory_count' => count($childCtx['memories'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 2 FAILED: child context', ['error' => $e->getMessage()]);
            $childCtx = ['profile' => null, 'memories' => [], 'summary' => null];
        }

        // 3. Search chat attachments (user + conversation scoped)
        try {
            $attachmentSources = $this->attachmentSearch->search(
                $userId,
                (int) $conversation->id,
                $userMessage
            );
            Log::info('[Chat] Step 3: Chat attachment search', [
                'chunks_found' => count($attachmentSources),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 3 FAILED: attachment search', ['error' => $e->getMessage()]);
            $attachmentSources = [];
        }

        // 4. Search knowledge base
        try {
            $knowledgeSources = $this->knowledgeSearch->search($userMessage);
            Log::info('[Chat] Step 4: Knowledge base search', [
                'chunks_found' => count($knowledgeSources),
            ]);

            foreach ($knowledgeSources as $i => $chunk) {
                Log::info('[Chat] Step 4: KB chunk #' . ($i + 1), [
                    'source_label'    => $chunk['source_label'] ?? null,
                    'document_id'     => $chunk['knowledge_document_id'] ?? null,
                    'similarity'      => round($chunk['similarity'] ?? 0, 4),
                    'content_preview' => mb_substr($chunk['content'] ?? '', 0, 200),
                ]);
            }

            if (empty($knowledgeSources)) {
                Log::warning('[Chat] Step 4: No knowledge chunks found — AI will answer without KB context');
            }
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 4 FAILED: knowledge search', ['error' => $e->getMessage()]);
            $knowledgeSources = [];
        }

        // 5. Search web fallback when enabled.
        $webSources = $this->searchWebSources($userMessage);

        // 6. Always include public medical/wellness references for App Review citation requirements.
        $medicalSources = $this->defaultMedicalSources();

        // 7. Merge sources
        $allSources = array_values(array_merge(
            $attachmentSources,
            $knowledgeSources,
            $webSources,
            $medicalSources
        ));
        Log::info('[Chat] Step 7: Total sources merged', [
            'total'             => count($allSources),
            'attachment_sources'=> count($attachmentSources),
            'knowledge_sources' => count($knowledgeSources),
            'web_sources'       => count($webSources),
            'medical_sources'   => count($medicalSources),
        ]);

        // 8. Build context string
        $sourceContext = $this->buildSourceContext($allSources);

        // 9. Fetch recent conversation history
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->take(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse()
            ->values();

        Log::info('[Chat] Step 9: History loaded', ['message_count' => $recentMessages->count()]);

        // 10. Build LLM messages array
        $systemPrompt = config('ai.system_prompt', '');

        if (!empty($childCtx['profile'])) {
            $systemPrompt .= "\n\nChild Profile:\n" . json_encode($childCtx['profile'], JSON_UNESCAPED_UNICODE);
        }
        if (!empty($childCtx['memories'])) {
            $systemPrompt .= "\n\nChild Memories:\n" . json_encode($childCtx['memories'], JSON_UNESCAPED_UNICODE);
        }
        if ($conversation->summary) {
            $systemPrompt .= "\n\nConversation Summary:\n" . $conversation->summary;
        }
        if ($sourceContext) {
            $systemPrompt .= "\n\nRelevant Context:" . $sourceContext;
        }
        if ($language === 'ar') {
            $systemPrompt .= "\n\nRespond in Arabic.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($recentMessages as $msg) {
            if ($msg->id === $userMsg->id) continue;
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        Log::info('[Chat] Step 10: LLM payload ready', [
            'total_messages'     => count($messages),
            'system_prompt_len'  => strlen($systemPrompt),
            'has_context'        => !empty($sourceContext),
        ]);

        // 11. Call LLM
        try {
            $reply = $this->llm->chat($messages);
            Log::info('[Chat] Step 11: LLM replied', ['reply_length' => strlen($reply)]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 11 FAILED: LLM call', ['error' => $e->getMessage()]);
            $reply = 'I\'m sorry, I encountered an error. Please try again.';
        }

        $reply = $this->appendResourcesBlock($reply, $allSources);

        // 12. Persist assistant message
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $reply,
            'sources'         => $allSources,
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

    private function searchWebSources(string $userMessage): array
    {
        if (! config('ai.web_search_enabled', false)) {
            return [];
        }

        try {
            $results = $this->webSearch->search($userMessage, [
                'count'      => 3,
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
                    'source_label' => 'WEB_SOURCE_' . ($index + 1),
                    'source_type'  => 'web',
                    'title'        => $source['title'] ?? 'Web source',
                    'url'          => $source['url'] ?? '',
                    'snippet'      => $source['snippet'] ?? '',
                    'content'      => $source['snippet'] ?? '',
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

            $label   = $source['source_label'] ?? 'MED_SOURCE_' . ($index + 1);
            $title   = $source['title'] ?? 'Medical reference';
            $url     = $source['url'] ?? '';
            $snippet = $source['snippet'] ?? '';

            $sources[] = [
                'source_label' => $label,
                'source_type'  => $source['source_type'] ?? 'medical_reference',
                'title'        => $title,
                'url'          => $url,
                'snippet'      => $snippet,
                'content'      => trim($snippet . ($url ? "\nURL: {$url}" : '')),
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
                    $lines[] = $labelText . ': ' . $source[$key];
                }
            }

            if (! empty($source['content']) && ! in_array($source['content'], $lines, true)) {
                $lines[] = 'Content: ' . $source['content'];
            }

            if (empty($lines)) {
                continue;
            }

            $sourceContext .= "\n\n[{$label}]\n" . implode("\n", $lines);
        }

        return $sourceContext;
    }

    private function appendResourcesBlock(string $reply, array $sources): string
    {
        $items = $this->resourceListItems($sources);

        if (empty($items)) {
            return trim($reply);
        }

        return rtrim($reply) . "\n\nResources:\n- " . implode("\n- ", $items);
    }

    private function resourceListItems(array $sources): array
    {
        $urlItems = [];
        $localItems = [];
        $seen = [];

        foreach ($sources as $source) {
            $label = $source['source_label'] ?? $source['label'] ?? null;
            $title = $this->sourceTitle($source);
            $url = trim((string) ($source['url'] ?? ''));

            if ($label === null || $title === '') {
                continue;
            }

            $key = $url !== '' ? $url : $label . '|' . $title . '|' . ($source['page_number'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $item = "[{$label}] {$title}";

            if (! empty($source['page_number'])) {
                $item .= ' (page ' . $source['page_number'] . ')';
            }

            if ($url !== '') {
                $urlItems[] = $item . ': ' . $url;
                continue;
            }

            $localItems[] = $item . ' (' . $this->sourceTypeName($source) . ')';
        }

        return array_merge($urlItems, array_slice($localItems, 0, 4));
    }

    private function sourceTitle(array $source): string
    {
        return trim((string) (
            $source['title']
            ?? $source['document_name']
            ?? $source['original_name']
            ?? $source['source_label']
            ?? ''
        ));
    }

    private function sourceTypeName(array $source): string
    {
        return match ($source['source_type'] ?? null) {
            'chat_attachment' => 'uploaded document',
            'knowledge_base' => 'knowledge base',
            default => 'source',
        };
    }
}

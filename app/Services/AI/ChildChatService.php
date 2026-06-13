<?php

namespace App\Services\AI;

use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateChildMemoryJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Support\Facades\Log;

class ChildChatService
{
    public function __construct(
        private LlmProviderInterface $llm,
        private ChildContextService $childContext,
        private KnowledgeSearchService $knowledgeSearch,
        private ChatAttachmentSearchService $attachmentSearch,
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

        // 5. Merge sources
        $allSources = array_merge($attachmentSources, $knowledgeSources);
        Log::info('[Chat] Step 5: Total sources merged', ['total' => count($allSources)]);

        // 6. Build context string
        $sourceContext = '';
        foreach ($allSources as $src) {
            $label          = $src['source_label'] ?? $src['label'] ?? 'SOURCE';
            $sourceContext .= "\n\n[{$label}]\n" . ($src['content'] ?? '');
        }

        // 7. Fetch recent conversation history
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->take(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse()
            ->values();

        Log::info('[Chat] Step 7: History loaded', ['message_count' => $recentMessages->count()]);

        // 8. Build LLM messages array
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

        Log::info('[Chat] Step 8: LLM payload ready', [
            'total_messages'     => count($messages),
            'system_prompt_len'  => strlen($systemPrompt),
            'has_context'        => !empty($sourceContext),
        ]);

        // 9. Call LLM
        try {
            $reply = $this->llm->chat($messages);
            Log::info('[Chat] Step 9: LLM replied', ['reply_length' => strlen($reply)]);
        } catch (\Throwable $e) {
            Log::error('[Chat] Step 9 FAILED: LLM call', ['error' => $e->getMessage()]);
            $reply = 'I\'m sorry, I encountered an error. Please try again.';
        }

        // 10. Persist assistant message
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $reply,
            'sources'         => !empty($allSources) ? $allSources : null,
        ]);

        Log::info('[Chat] Step 10: Assistant message saved', ['message_id' => $assistantMsg->id]);

        // 11. Increment count and dispatch background jobs
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
}

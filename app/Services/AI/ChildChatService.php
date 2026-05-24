<?php

namespace App\Services\AI;

use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateChildMemoryJob;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\VectorSearchRepositoryInterface;
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
        // 1. Persist user message
        $userMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        // 2. Build child context
        $childCtx = $this->childContext->build($childId, $userId);

        // 3. Search chat attachments first (user-scoped)
        $attachmentSources = $this->attachmentSearch->search(
            $userId,
            (int) $conversation->id,
            $userMessage
        );

        // 4. Search knowledge base
        $knowledgeSources = $this->knowledgeSearch->search($userMessage);

        // 5. Merge sources (attachment first, then knowledge)
        $allSources = array_merge($attachmentSources, $knowledgeSources);

        // 6. Build context string from sources
        $sourceContext = '';
        foreach ($allSources as $src) {
            $sourceContext .= "\n\n[{$src['label']}]\n{$src['content']}";
        }

        // 7. Fetch recent messages for conversation history
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->take(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse()
            ->values();

        // 8. Build messages array for LLM
        $systemPrompt = config('ai.system_prompt', '');
        if ($childCtx['profile']) {
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

        // 9. Call LLM
        try {
            $reply = $this->llm->chat($messages);
        } catch (\Throwable $e) {
            Log::error('ChildChatService LLM error', ['error' => $e->getMessage()]);
            $reply = 'I\'m sorry, I encountered an error. Please try again.';
        }

        // 10. Persist assistant message
        $assistantMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $reply,
            'sources'         => !empty($allSources) ? $allSources : null,
        ]);

        // 11. Update conversation message count & dispatch background jobs
        $conversation->increment('message_count');
        $conversation->touch();

        $count = $conversation->fresh()->message_count ?? 0;

        if ($childId && $count > 0 && $count % 5 === 0) {
            UpdateChildMemoryJob::dispatch($conversation->id, $childId);
        }

        if ($count > 0 && $count % 10 === 0) {
            SummarizeConversationJob::dispatch($conversation->id);
        }

        return $assistantMsg;
    }
}

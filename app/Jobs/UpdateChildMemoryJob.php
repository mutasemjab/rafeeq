<?php

namespace App\Jobs;

use App\Models\ChildMemory;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateChildMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int  $conversationId,
        private ?int $childId
    ) {
    }

    public function handle(): void
    {
        // Nothing to do if there is no child attached to this conversation.
        if (!$this->childId) {
            return;
        }

        try {
            $conversation = Conversation::with('messages')->findOrFail($this->conversationId);

            // Fetch the 10 most recent messages in chronological order.
            $recentMessages = Message::where('conversation_id', $this->conversationId)
                ->latest()
                ->take(10)
                ->get()
                ->reverse()
                ->values();

            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            // Build the extraction prompt.
            $messageLines = $recentMessages->map(function (Message $msg) {
                $role = ucfirst($msg->role ?? 'user');
                return "{$role}: {$msg->content}";
            })->implode("\n");

            $prompt = <<<PROMPT
You are an assistant that extracts important memories about a child from a conversation.

Conversation:
{$messageLines}

Extract any important, lasting facts about the child mentioned in this conversation.
Return a JSON object with a "memories" key containing an array of objects. Each memory object must have:
- "type": string (e.g. "diagnosis", "behavior", "school", "therapy", "medical", "communication", "general")
- "title": short string label
- "content": the actual memory text
- "confidence": float between 0 and 1

Only extract meaningful, factual memories. If none are found, return {"memories": []}.
PROMPT;

            $raw = $llm->chatJson([['role' => 'user', 'content' => $prompt]]);

            $memories = $raw['memories'] ?? [];

            if (empty($memories)) {
                return;
            }

            // Load existing memory content to avoid duplicates.
            $existing = ChildMemory::where('child_id', $this->childId)
                ->pluck('content')
                ->toArray();

            foreach ($memories as $memoryData) {
                $content = $memoryData['content'] ?? '';

                if (empty($content)) {
                    continue;
                }

                // Skip if a very similar memory already exists.
                foreach ($existing as $existingContent) {
                    if (str_contains(strtolower($existingContent), strtolower(substr($content, 0, 50)))) {
                        continue 2;
                    }
                }

                ChildMemory::create([
                    'child_id'          => $this->childId,
                    'user_id'           => $conversation->user_id,
                    'type'              => $memoryData['type'] ?? 'general',
                    'title'             => $memoryData['title'] ?? '',
                    'content'           => $content,
                    'confidence'        => $memoryData['confidence'] ?? 0.8,
                    'source'            => 'ai_extraction',
                    'source_message_id' => null,
                ]);

                // Add to local list to prevent duplicate insertion in the same run.
                $existing[] = $content;
            }
        } catch (Throwable $e) {
            Log::error('UpdateChildMemoryJob failed', [
                'conversation_id' => $this->conversationId,
                'child_id'        => $this->childId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Jobs;

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

class SummarizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $conversationId)
    {
    }

    public function handle(): void
    {
        try {
            $conv = Conversation::findOrFail($this->conversationId);

            $msgCount = Message::where('conversation_id', $this->conversationId)->count();

            // Only summarize at every 10th message milestone.
            if ($msgCount % 10 !== 0) {
                return;
            }

            // Fetch the 20 most recent messages in chronological order.
            $messages = Message::where('conversation_id', $this->conversationId)
                ->latest()
                ->take(20)
                ->get()
                ->reverse()
                ->values();

            if ($messages->isEmpty()) {
                return;
            }

            /** @var LlmProviderInterface $llm */
            $llm = app(LlmProviderInterface::class);

            $messageLines = $messages->map(function (Message $msg) {
                $role = ucfirst($msg->role ?? 'user');
                return "{$role}: {$msg->content}";
            })->implode("\n");

            $prompt = <<<PROMPT
Please provide a brief, concise summary (2-4 sentences) of the following conversation. Focus on the main topics discussed and any important outcomes or decisions made.

Conversation:
{$messageLines}

Summary:
PROMPT;

            $summary = $llm->chat([['role' => 'user', 'content' => $prompt]]);

            $conv->update(['summary' => $summary]);
        } catch (Throwable $e) {
            Log::error('SummarizeConversationJob failed', [
                'conversation_id' => $this->conversationId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}

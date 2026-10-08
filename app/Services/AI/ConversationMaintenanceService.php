<?php

namespace App\Services\AI;

use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateChildMemoryJob;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationMaintenanceService
{
    public function dispatch(Conversation $conversation, bool $alreadyResponded = false): void
    {
        if (! app(PersonContextService::class)->canProcess($conversation, (int) $conversation->user_id)) {
            return;
        }
        $count = $conversation->fresh()?->message_count ?? 0;
        $jobs = [];
        if ($conversation->child_id && $count > 0 && $count % 5 === 0) {
            $jobs[] = new UpdateChildMemoryJob($conversation->id, $conversation->child_id);
        }
        if ($count > 0 && $count % 10 === 0) {
            $jobs[] = new SummarizeConversationJob($conversation->id);
        }
        foreach ($jobs as $job) {
            try {
                if (config('queue.default') !== 'sync') {
                    dispatch($job);
                } elseif ($alreadyResponded) {
                    // During HTTP termination, registering another termination
                    // callback may never run. The answer is already published.
                    dispatch_sync($job);
                } else {
                    dispatch($job)->afterResponse();
                }
            } catch (Throwable $error) {
                Log::warning('ai.maintenance.dispatch_failed', ['conversation_id' => $conversation->id, 'exception' => $error::class]);
            }
        }
    }
}

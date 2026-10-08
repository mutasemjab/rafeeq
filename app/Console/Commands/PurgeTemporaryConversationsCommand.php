<?php

namespace App\Console\Commands;

use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Services\AI\ChatUsageService;
use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTemporaryConversationsCommand extends Command
{
    protected $signature = 'conversations:purge-expired {--limit=100}';
    protected $description = 'Remove expired temporary chat content and private attachments while retaining daily quota counts';

    public function handle(): int
    {
        $ids = Conversation::withoutGlobalScope('unexpired')->withTrashed()->where('is_temporary', true)
            ->where('expires_at', '<=', now())->limit(max(1, min(1000, (int) $this->option('limit'))))->pluck('id');
        $removed = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$removed): void {
                $conversation = Conversation::withoutGlobalScope('unexpired')->withTrashed()->whereKey($id)->lockForUpdate()->first();
                if (! $conversation || ! $conversation->is_temporary || $conversation->expires_at->isFuture()) { return; }
                app(ChatUsageService::class)->retainDeletedUsage((int) $conversation->user_id, [$conversation->id]);
                foreach (ChatAttachment::withTrashed()->where('conversation_id', $id)->where('user_id', $conversation->user_id)->lockForUpdate()->get() as $file) {
                    app(PrivateDocumentStorage::class)->delete($file);
                    $file->chunks()->delete();
                    $file->forceDelete();
                }
                $conversation->forceDelete();
                $removed++;
            });
        }
        DB::table('chat_usage_adjustments')->where('usage_date', '<', today()->subDay()->toDateString())->delete();
        $this->info('Expired temporary conversations removed: '.$removed);
        return self::SUCCESS;
    }
}

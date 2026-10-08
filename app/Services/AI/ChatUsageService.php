<?php

namespace App\Services\AI;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatUsageService
{
    public function forUser(User $user): array
    {
        $limit = $user->activeSubscription()?->plan?->ai_messages_per_day;
        $used = Message::where('user_id', $user->id)->where('role', 'user')
            ->where('created_at', '>=', today())->where('created_at', '<', today()->addDay())->count();
        $used += (int) DB::table('chat_usage_adjustments')->where('user_id', $user->id)->where('usage_date', today()->toDateString())->value('used');

        return [
            'used' => $used,
            'limit' => $limit === null ? null : (int) $limit,
            'remaining' => $limit === null ? null : max(0, (int) $limit - $used),
            'resets_at' => today()->addDay()->toISOString(),
        ];
    }

    /** Keep only daily counts when privacy cleanup removes message content. */
    public function retainDeletedUsage(int $userId, array $conversationIds): void
    {
        User::whereKey($userId)->lockForUpdate()->firstOrFail();
        $counts = Message::where('user_id', $userId)->where('role', 'user')->whereIn('conversation_id', $conversationIds)
            ->where('created_at', '>=', today()->subDay())->selectRaw('DATE(created_at) as usage_date, COUNT(*) as used')->groupByRaw('DATE(created_at)')->get();
        foreach ($counts as $count) {
            $key = ['user_id' => $userId, 'usage_date' => $count->usage_date];
            $previous = (int) DB::table('chat_usage_adjustments')->where($key)->value('used');
            DB::table('chat_usage_adjustments')->updateOrInsert($key, ['used' => $previous + (int) $count->used]);
        }
    }
}

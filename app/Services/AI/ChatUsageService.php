<?php

namespace App\Services\AI;

use App\Models\Message;
use App\Models\User;

class ChatUsageService
{
    public function forUser(User $user): array
    {
        $limit = $user->activeSubscription()?->plan?->ai_messages_per_day;
        $used = Message::where('user_id', $user->id)->where('role', 'user')
            ->where('created_at', '>=', today())->where('created_at', '<', today()->addDay())->count();

        return [
            'used' => $used,
            'limit' => $limit === null ? null : (int) $limit,
            'remaining' => $limit === null ? null : max(0, (int) $limit - $used),
            'resets_at' => today()->addDay()->toISOString(),
        ];
    }
}

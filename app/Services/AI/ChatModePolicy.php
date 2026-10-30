<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatModePolicy
{
    public function canUseExtraThinking(User $user): bool
    {
        return $user->subscriptions()->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('plan', fn ($q) => $q->where('is_active', true)->where('type', 'pro'))
            ->exists();
    }

    public function authorize(User $user, bool $extraThinking, string $language): void
    {
        if ($extraThinking && ! $this->canUseExtraThinking($user)) {
            throw new HttpResponseException(response()->json([
                'error_code' => 'EXTRA_THINKING_REQUIRES_PREMIUM',
                'message' => $language === 'ar' ? 'التفكير المتعمق متاح مع اشتراك بريميوم نشط.' : 'Extra thinking requires an active premium subscription.',
                'retryable' => false,
            ], 403));
        }
    }
}

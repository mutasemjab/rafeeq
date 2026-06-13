<?php

namespace App\Services\Privacy;

use App\Models\User;

class AiConsentService
{
    public function snapshot(User $user): array
    {
        return $user->aiConsentSnapshot();
    }

    public function save(User $user, bool $hasAiConsent, ?string $version = null): array
    {
        if (! $hasAiConsent) {
            $user->forceFill([
                'ai_consent_accepted_at' => null,
                'ai_consent_version' => null,
            ])->save();

            return $this->snapshot($user->fresh());
        }

        $user->forceFill([
            'ai_consent_accepted_at' => now(),
            'ai_consent_version' => $version ?: (string) config('privacy.ai_consent_version', '1.0'),
        ])->save();

        return $this->snapshot($user->fresh());
    }

    public function requiredMessage(): string
    {
        return 'AI data-sharing consent is required before using AI features.';
    }
}

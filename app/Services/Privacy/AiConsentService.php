<?php

namespace App\Services\Privacy;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\User;
use App\Services\Documents\PrivateFileProcessingDispatcher;

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

        $alreadyAccepted = $user->hasAiConsent();
        $user->forceFill([
            'ai_consent_accepted_at' => now(),
            'ai_consent_version' => $version ?: (string) config('privacy.ai_consent_version', '1.0'),
        ])->save();

        if (! $alreadyAccepted) {
            // Files may have been uploaded before opting in. Limit work on
            // this request, using a durable queue when configured. Old
            // accounts with more files can use the explicit backfill command.
            $user->childDocuments()
                ->where('status', 'uploaded')
                ->whereHas('child', fn ($query) => $query->where('user_id', $user->id))
                ->latest('id')
                ->limit(20)
                ->pluck('id')
                ->each(function ($id): void {
                    app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessChildDocumentJob::class, (int) $id);
                });
        }

        return $this->snapshot($user->fresh());
    }

    public function requiredMessage(): string
    {
        return 'AI data-sharing consent is required before using AI features.';
    }
}

<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\PersonProfile;

class PersonContextService
{
    /** Fresh consent and ownership checks are shared by foreground and queued work. */
    public function canProcess(Conversation $conversation, int $userId): bool
    {
        if ((int) $conversation->user_id !== $userId || $conversation->trashed()) {
            return false;
        }
        if ($conversation->person_profile_id === null) {
            return true; // Legacy child/general conversations keep their existing consent rules.
        }
        $person = PersonProfile::whereKey($conversation->person_profile_id)->where('user_id', $userId)->first();

        return $person !== null && $person->hasProcessingConsent() && $person->user?->hasAiConsent()
            && ($person->legacy_child_id === null
                ? $conversation->child_id === null
                : (int) $person->legacy_child_id === (int) $conversation->child_id
                    && $person->legacyChild()->where('user_id', $userId)->exists());
    }

    public function assertCanProcess(Conversation $conversation, int $userId): void
    {
        abort_unless($this->canProcess($conversation, $userId), 403, 'Person consent or access is unavailable.');
    }

    public function build(Conversation $conversation, int $userId): array
    {
        $this->assertCanProcess($conversation, $userId);
        $person = PersonProfile::whereKey($conversation->person_profile_id)->where('user_id', $userId)->firstOrFail();
        $profile = [
            'person_profile_id' => $person->id, 'age_months' => $person->effectiveAgeMonths(),
            'age_group' => $person->ageGroup(), 'relationship' => $person->relationship,
            'preferred_language' => $person->preferred_language,
            'communication_preferences' => $person->communication_preferences,
            'diagnosis' => $person->reported_diagnosis, 'diagnosis_source' => $person->diagnosis_source,
            'diagnosis_provenance' => 'Reported information; not independently confirmed by AI.',
        ];
        $previous = Conversation::where('user_id', $userId)->where('person_profile_id', $person->id)
            ->where('id', '!=', $conversation->id)->latest('updated_at')->take(3)->get();
        $context = [
            'profile' => $profile, 'memories' => $person->memories()->where('user_id', $userId)
                ->where('status', 'active')->orderByDesc('last_confirmed_at')->latest('id')
                ->take((int) config('ai.max_child_memories', 20))->get()->toArray(),
            'summary' => $previous->pluck('summary')->filter()->implode("\n"),
            'previous_progress' => $previous->map(fn ($item): array => (array) data_get($item->case_state, 'progress', []))->filter()->values()->all(),
        ];
        $context['case_brief'] = CaseBriefService::build($context, (array) $conversation->case_state, $conversation->summary);

        return $context;
    }
}

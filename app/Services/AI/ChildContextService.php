<?php

namespace App\Services\AI;

use App\Models\Child;
use App\Models\ChildMemory;
use App\Models\Conversation;

class ChildContextService
{
    /**
     * Build the child context array for AI consumption.
     *
     * @param  int|null  $childId
     * @param  int       $userId
     * @return array{profile: array|null, memories: array, summary: string|null}
     */
    public function build(?int $childId, int $userId): array
    {
        if (!$childId) {
            return [
                'profile'  => null,
                'memories' => [],
                'summary'  => null,
            ];
        }

        $child = Child::where('id', $childId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $memories = ChildMemory::where('child_id', $childId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('last_confirmed_at')
            ->latest('id')
            ->take((int) config('ai.max_child_memories'))
            ->get();

        $profile = $child->toArray();
        if ($child->birth_date !== null) {
            $profile['age_months'] = $child->birth_date->diffInMonths(now());
            $profile['age_years'] = round($profile['age_months'] / 12, 1);
        } elseif ($child->age !== null) {
            $profile['age_months'] = ((int) $child->age) * 12;
            $profile['age_years'] = (int) $child->age;
        }

        $longitudinalSummary = Conversation::query()
            ->where('child_id', $childId)
            ->where('user_id', $userId)
            ->whereNotNull('summary')
            ->latest('updated_at')
            ->take(3)
            ->pluck('summary')
            ->filter()
            ->implode("\n");

        return [
            'profile'  => $profile,
            'memories' => $memories->toArray(),
            'summary'  => $longitudinalSummary !== '' ? $longitudinalSummary : null,
        ];
    }
}

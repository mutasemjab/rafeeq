<?php

namespace App\Services\AI;

use App\Models\Child;
use App\Models\ChildMemory;

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
            ->latest()
            ->take((int) config('ai.max_child_memories'))
            ->get();

        return [
            'profile'  => $child->toArray(),
            'memories' => $memories->toArray(),
            'summary'  => null,
        ];
    }
}

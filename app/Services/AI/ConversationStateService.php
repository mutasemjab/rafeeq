<?php

namespace App\Services\AI;

use App\Models\Conversation;

class ConversationStateService
{
    public function recordPlan(Conversation $conversation, array $plan, array $safety): void
    {
        $previous = is_array($conversation->case_state) ? $conversation->case_state : [];
        $state = array_merge($previous, [
            'domain' => $plan['domain'] ?? ($previous['domain'] ?? 'general'),
            'case_specific' => (bool) ($plan['case_specific'] ?? false),
            'risk_level' => $plan['risk_level'] ?? 'low',
            'missing_fields' => array_values($plan['missing_fields'] ?? []),
            'last_action' => $plan['action'] ?? null,
            'last_reason' => $plan['reason'] ?? null,
            'last_safety_level' => $safety['level'] ?? 'routine',
            'follow_up_needed' => (bool) ($plan['follow_up_needed'] ?? false),
            'updated_at' => now()->toISOString(),
        ]);

        $conversation->forceFill([
            'active_domain' => $state['domain'],
            'case_state' => $state,
            'next_question' => ($plan['action'] ?? null) === 'ask_clarification'
                ? ($plan['question'] ?? null)
                : null,
            'last_planned_at' => now(),
        ])->save();
    }

    public function recordAnswer(Conversation $conversation, ?string $nextQuestion, array $outcome = []): void
    {
        $state = is_array($conversation->case_state) ? $conversation->case_state : [];
        $state['last_action'] = 'answer';
        $state['last_answered_at'] = now()->toISOString();
        $state['follow_up_needed'] = $nextQuestion !== null;
        if ($outcome !== []) {
            $state['last_outcome_plan'] = $outcome;
        }

        $conversation->forceFill([
            'case_state' => $state,
            'next_question' => $nextQuestion,
        ])->save();
    }
}

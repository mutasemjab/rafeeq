<?php

namespace App\Services\AI;

use App\Models\Conversation;

class ConversationStateService
{
    public function recordPlan(Conversation $conversation, array $plan, array $safety): void
    {
        $previous = is_array($conversation->case_state) ? $conversation->case_state : [];
        $askedQuestions = $this->askedQuestions($previous);
        if (($plan['action'] ?? null) === 'ask_clarification' && is_string($plan['question'] ?? null)) {
            $askedQuestions = $this->appendQuestion($askedQuestions, (string) $plan['question'], [
                'target' => $plan['question_target'] ?? null,
                'anchor' => $plan['question_anchor'] ?? null,
                'decision_impact' => $plan['expected_answer_use'] ?? null,
                'source' => 'clarification',
            ]);
        }

        $previousKnownFacts = is_array($previous['known_facts'] ?? null)
            ? $previous['known_facts']
            : [];
        $plannedKnownFacts = is_array($plan['known_facts'] ?? null)
            ? $plan['known_facts']
            : [];
        $knownFacts = collect([
            ...$previousKnownFacts,
            ...$plannedKnownFacts,
        ])->filter(fn ($fact): bool => is_string($fact) && trim($fact) !== '')
            ->map(fn (string $fact): string => mb_substr(trim($fact), 0, 300))
            ->unique(fn (string $fact): string => mb_strtolower($fact))
            ->take(-30)
            ->values()
            ->all();

        $state = array_merge($previous, [
            'domain' => $plan['domain'] ?? ($previous['domain'] ?? 'general'),
            'case_specific' => (bool) ($plan['case_specific'] ?? false),
            'risk_level' => $plan['risk_level'] ?? 'low',
            'known_facts' => $knownFacts,
            'missing_fields' => array_values($plan['missing_fields'] ?? []),
            'decision_to_make' => $plan['decision_to_make'] ?? null,
            'last_question_target' => $plan['question_target'] ?? null,
            'last_question_anchor' => $plan['question_anchor'] ?? null,
            'asked_questions' => $askedQuestions,
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
        if ($nextQuestion !== null) {
            $state['asked_questions'] = $this->appendQuestion(
                $this->askedQuestions($state),
                $nextQuestion,
                [
                    'target' => $outcome['purpose'] ?? null,
                    'anchor' => $outcome['anchor'] ?? null,
                    'decision_impact' => $outcome['decision_impact'] ?? null,
                    'source' => 'follow_up',
                ]
            );
        }

        $conversation->forceFill([
            'case_state' => $state,
            'next_question' => $nextQuestion,
        ])->save();
    }

    private function askedQuestions(array $state): array
    {
        return collect($state['asked_questions'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && is_string($item['question'] ?? null))
            ->take(-19)
            ->values()
            ->all();
    }

    private function appendQuestion(array $questions, string $question, array $metadata): array
    {
        $question = trim($question);
        if ($question === '') {
            return $questions;
        }

        $normalized = $this->normalizeQuestion($question);
        $alreadyStored = collect($questions)->contains(
            fn (array $item): bool => $this->normalizeQuestion((string) ($item['question'] ?? '')) === $normalized
        );
        if (! $alreadyStored) {
            $questions[] = array_merge($metadata, [
                'question' => mb_substr($question, 0, 500),
                'asked_at' => now()->toISOString(),
            ]);
        }

        return array_slice($questions, -20);
    }

    private function normalizeQuestion(string $question): string
    {
        $question = mb_strtolower($question);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $question) ?? $question;
    }
}

<?php

namespace App\Services\AI;

use App\Models\Conversation;

class ConversationStateService
{
    public function recordPlan(Conversation $conversation, array $plan, array $safety, ?int $sourceMessageId = null, ?string $latestMessage = null): void
    {
        $previous = is_array($conversation->case_state) ? $conversation->case_state : [];
        $askedQuestions = $this->askedQuestions($previous);
        if (($plan['action'] ?? null) === 'ask_clarification' && is_string($plan['question'] ?? null)) {
            $askedQuestions = $this->appendQuestion($askedQuestions, (string) $plan['question'], [
                'target' => $plan['question_target'] ?? null,
                'anchor' => $plan['question_anchor'] ?? null,
                'decision_impact' => $plan['expected_answer_use'] ?? null,
                'source' => 'clarification',
                'scope' => self::questionScope($plan),
                'observation_round' => (int) ($previous['observation_round'] ?? 0),
            ]);
        }

        $plannedKnownFacts = is_array($plan['known_facts'] ?? null)
            ? $plan['known_facts']
            : [];
        // The planner supplies a CURRENT snapshot. Appending old free-text
        // facts forever resurrects values after an explicit correction.
        $facts = is_array($previous['fact_index'] ?? null) ? $previous['fact_index'] : [];
        $superseded = (array) ($previous['superseded_facts'] ?? []);
        foreach (array_slice((array) ($plan['memory_candidates'] ?? []), 0, 8) as $candidate) {
            if (! is_array($candidate) || $latestMessage === null) {
                continue;
            }
            $key = ChildMemoryManager::canonicalKey((string) ($candidate['key'] ?? ''));
            $content = trim((string) ($candidate['content'] ?? ''));
            $evidence = trim((string) ($candidate['evidence'] ?? ''));
            if ($key === '' || $content === '' || $evidence === ''
                || mb_stripos($latestMessage, $evidence) === false
                || (float) ($candidate['confidence'] ?? 0) < (float) config('ai.memory_minimum_confidence', 0.78)) {
                continue;
            }
            if ($sourceMessageId !== null && isset($facts[$key]['source_message_id'])
                && $sourceMessageId < $facts[$key]['source_message_id']) {
                continue;
            }
            if (isset($facts[$key]) && ($facts[$key]['content'] ?? null) !== $content) {
                $superseded[] = array_merge($facts[$key], ['key' => $key, 'status' => 'superseded']);
            }
            $facts[$key] = [
                'content' => mb_substr($content, 0, 600), 'evidence' => mb_substr($evidence, 0, 500),
                'source_message_id' => $sourceMessageId, 'reported_at' => now()->toISOString(),
                'fact_status' => $candidate['fact_status'] ?? 'reported_concern',
            ];
        }
        $supersededContents = array_diff(array_column($superseded, 'content'), array_column($facts, 'content'));
        $knownFacts = collect($plannedKnownFacts)
            ->merge(array_column($facts, 'content'))
            ->filter(fn ($fact): bool => is_string($fact) && trim($fact) !== '' && ! in_array(trim($fact), $supersededContents, true))
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
            'fact_index' => array_slice($facts, -30, null, true),
            'superseded_facts' => array_slice($superseded, -20),
            'missing_fields' => array_values($plan['missing_fields'] ?? []),
            'decision_to_make' => $plan['decision_to_make'] ?? null,
            'last_question_target' => $plan['question_target'] ?? null,
            'last_question_anchor' => $plan['question_anchor'] ?? null,
            'asked_questions' => $askedQuestions,
            'last_action' => $plan['action'] ?? null,
            'last_reason' => $plan['reason'] ?? null,
            'last_safety_level' => $safety['level'] ?? 'routine',
            'follow_up_needed' => (bool) ($plan['follow_up_needed'] ?? false),
            'question_scope' => self::questionScope($plan),
            'consecutive_clarifications' => ($plan['action'] ?? null) === 'ask_clarification'
                ? (int) ($previous['consecutive_clarifications'] ?? 0) + 1 : 0,
            'updated_at' => now()->toISOString(),
        ]);
        if (($plan['outcome_reported'] ?? false) === true && $latestMessage !== null) {
            $reports = (array) ($state['progress']['outcome_reports'] ?? []);
            $reports[] = ['content' => mb_substr($latestMessage, 0, 1200), 'source_message_id' => $sourceMessageId, 'reported_at' => now()->toISOString()];
            $state['progress']['outcome_reports'] = array_slice($reports, -5);
            $state['progress']['status'] = 'outcome_reported';
            $state['observation_round'] = (int) ($previous['observation_round'] ?? 0) + 1;
        }

        $conversation->forceFill([
            'active_domain' => $state['domain'],
            'case_state' => $state,
            'next_question' => ($plan['action'] ?? null) === 'ask_clarification'
                ? ($plan['question'] ?? null)
                : null,
            'last_planned_at' => now(),
        ])->save();
    }

    public function recordAnswer(Conversation $conversation, ?string $nextQuestion, array $outcome = [], string $action = 'answer', ?string $answerText = null): void
    {
        $state = is_array($conversation->case_state) ? $conversation->case_state : [];
        $state['last_action'] = $action === 'refer_to_specialist' ? 'refer_to_specialist' : 'answer';
        if ($action === 'refer_to_specialist') {
            $state['last_referral_at'] = now()->toISOString();
        }
        $state['last_answered_at'] = now()->toISOString();
        $state['follow_up_needed'] = $nextQuestion !== null;
        $state['consecutive_clarifications'] = 0;
        if ($answerText !== null && trim($answerText) !== '' && $action !== 'refer_to_specialist'
            && ($state['case_specific'] ?? false) === true) {
            $previousSteps = (array) ($state['progress']['previous_steps'] ?? []);
            if (! empty($state['progress']['recommended_step']) && $state['progress']['recommended_step'] !== trim($answerText)) {
                $previousSteps[] = [
                    'content' => $state['progress']['recommended_step'],
                    'recommended_at' => $state['progress']['recommended_at'] ?? null,
                ];
            }
            $state['progress'] = array_merge((array) ($state['progress'] ?? []), [
                'status' => ($outcome['wait_for_observation'] ?? false) ? 'awaiting_observation' : 'proposed',
                'decision' => $state['decision_to_make'] ?? null,
                'recommended_step' => mb_substr(trim($answerText), 0, 2200),
                'recommended_at' => now()->toISOString(),
                'observation_question' => $nextQuestion,
                'waiting_for' => $nextQuestion === null ? null : (($outcome['wait_for_observation'] ?? false) ? 'observation' : 'answer_now'),
                'previous_steps' => array_slice($previousSteps, -3),
            ]);
        }
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
                    'scope' => $state['question_scope'] ?? null,
                    'observation_round' => (int) ($state['observation_round'] ?? 0),
                    'wait_for_observation' => (bool) ($outcome['wait_for_observation'] ?? false),
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
                && (! isset($item['scope'], $metadata['scope']) || $item['scope'] === $metadata['scope'])
                && (int) ($item['observation_round'] ?? 0) === (int) ($metadata['observation_round'] ?? 0)
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

    public static function questionScope(array $plan): string
    {
        $concepts = collect($plan['problem_types'] ?? [])->filter(fn ($item): bool => is_string($item))
            ->map(fn (string $item): string => mb_strtolower(trim($item)))->unique()->sort()->values()->all();

        return mb_strtolower((string) ($plan['domain'] ?? 'general')).':'.implode(',', $concepts);
    }
}

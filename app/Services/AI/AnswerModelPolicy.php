<?php

namespace App\Services\AI;

class AnswerModelPolicy
{
    /** Keep the configured primary model for high-risk or complex support. */
    public function options(array $plan, string $message): array
    {
        if (($plan['response_mode'] ?? null) === 'deep') {
            return [
                'model' => (string) config('ai.answer_model'),
                'reasoning_effort' => (string) config('ai.answer_reasoning_effort', 'medium'),
                'max_completion_tokens' => (int) config('ai.answer_max_output_tokens', 1400),
            ];
        }
        if (($plan['response_mode'] ?? null) === 'fast' && ! $this->requiresCarefulReview($plan, $message)) {
            $effort = (string) config('ai.fast_answer_reasoning_effort', 'none');
            $personalized = ($plan['case_specific'] ?? false) === true;
            // A weak first draft repeatedly needs a full rewrite and another
            // verification call. Use the primary model for personalized book
            // answers; the independent review and repair path still apply.
            $groundedPersonalAnswer = $personalized
                && data_get($plan, 'knowledge_grounding.mode') === 'internal';
            return [
                'model' => (string) config($groundedPersonalAnswer ? 'ai.answer_model' : 'ai.fast_answer_model'),
                'reasoning_effort' => $personalized && $effort === 'none' ? 'low' : $effort,
                'max_completion_tokens' => max($personalized ? 1600 : 0, (int) config('ai.fast_answer_max_output_tokens', 800)),
            ];
        }
        $routine = trim((string) config('ai.routine_answer_model', ''));
        if ($routine === '' || ($plan['risk_level'] ?? 'high') === 'high' || ($plan['action'] ?? '') === 'refer_to_specialist' || ($plan['after_referral'] ?? false)) {
            return [];
        }
        if (preg_match('/\b(?:medicat|medicine|dose|dosage|withdraw|overdose|drug)\w*\b|دواء|دوائي|جرع|انسحاب|مخدر/iu', $message)) {
            return [];
        }
        $complex = ['psychosis', 'bipolar', 'eating', 'substance', 'cognition', 'personality', 'borderline', 'paraphilic', 'sexual_gender', 'trauma', 'dissociation'];
        foreach ($complex as $concept) {
            if (str_contains((string)($plan['domain']??''),$concept)) { return []; }
        }
        if (preg_match('/psychosis|hallucinat|delusion|bipolar|mania|ذهان|هلاوس|هوس|تسمم|خرف|إكراه|اعتداء/iu',$message)) { return []; }
        $routes = (array) data_get($plan, 'pathway_state.active_pathways', []);
        if (array_intersect($routes, $complex) !== []) { return []; }
        return ['model' => $routine];
    }

    public function requiresCarefulReview(array $plan, string $message): bool
    {
        if (($plan['risk_level'] ?? 'high') === 'high' || ($plan['action'] ?? '') === 'refer_to_specialist' || ($plan['after_referral'] ?? false)) return true;
        if (preg_match('/\b(?:medicat|medicine|dose|dosage|withdraw|overdose|drug)\w*\b|دواء|دوائي|جرع|انسحاب|مخدر|psychosis|hallucinat|delusion|bipolar|mania|ذهان|هلاوس|هوس|تسمم|خرف|إكراه|اعتداء/iu', $message)) return true;
        foreach (['psychosis', 'bipolar', 'eating', 'substance', 'cognition', 'personality', 'borderline', 'paraphilic', 'sexual_gender', 'trauma', 'dissociation'] as $concept) {
            if (str_contains((string) ($plan['domain'] ?? ''), $concept)
                || in_array($concept, (array) data_get($plan, 'pathway_state.active_pathways', []), true)) return true;
        }
        return false;
    }
}

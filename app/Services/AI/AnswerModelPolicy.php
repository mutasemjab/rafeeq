<?php

namespace App\Services\AI;

class AnswerModelPolicy
{
    /** Keep the configured primary model for high-risk or complex support. */
    public function options(array $plan, string $message): array
    {
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
}

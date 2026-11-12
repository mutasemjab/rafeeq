<?php

namespace App\Services\AI;

class PersonalizedModelRouter
{
    /** Missing/uncertain risk information must never select the cheaper route. */
    public function eligible(array $plan, string $message, array $context = []): bool
    {
        return config('ai.adaptive_personalized_routing', true)
            && ($plan['response_mode'] ?? '') === 'fast'
            && ($plan['case_specific'] ?? false) === true
            && ($plan['action'] ?? '') === 'answer'
            && ($plan['risk_level'] ?? '') === 'low'
            && is_numeric($plan['confidence'] ?? null) && $plan['confidence'] >= 0.9
            && empty($plan['critical_gap']) && empty($plan['after_referral'])
            && empty($plan['cost_routing_force_primary']) && empty($context['reply_feedback'])
            && empty($plan['previous_evidence_gaps'])
            && !data_get($plan, 'knowledge_grounding.coverage_escalated', false)
            && !app(AnswerModelPolicy::class)->requiresCarefulReview($plan, $message);
    }

    public function grounded(array $plan, string $message, array $context = []): bool
    {
        return $this->eligible($plan, $message, $context)
            && data_get($plan, 'knowledge_grounding.mode') === 'internal';
    }

    public function model(): string
    {
        return (string) config('ai.balanced_personalized_model', 'gpt-6-sol');
    }
}

<?php

namespace App\Services\Privacy;

class PrivacySettingsService
{
    public function snapshot(): array
    {
        return [
            'policy_url' => config('privacy.policy_url'),
            'ai_consent_required' => true,
            'ai_consent_version' => (string) config('privacy.ai_consent_version', '1.0'),
            'third_party_ai_provider' => (string) config('privacy.ai_provider_name', 'OpenAI'),
            'summary' => [
                'data_collected' => array_values(config('privacy.summary.data_collected', [])),
                'data_collection_methods' => array_values(config('privacy.summary.data_collection_methods', [])),
                'data_usage' => array_values(config('privacy.summary.data_usage', [])),
                'ai_data_sharing' => array_values(config('privacy.summary.ai_data_sharing', [])),
            ],
        ];
    }
}

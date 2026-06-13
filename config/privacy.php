<?php

return [
    'ai_consent_version' => env('AI_CONSENT_VERSION', '1.0'),
    'policy_url' => env('PRIVACY_POLICY_URL'),
    'ai_provider_name' => env('AI_PROVIDER_NAME', 'OpenAI'),
    'summary' => [
        'data_collected' => [
            'Account details such as name, email, phone, and authentication provider data.',
            'Child profiles, documents, memories, messages, and uploaded conversation attachments.',
            'Device and notification token data needed for login state and push notifications.',
        ],
        'data_collection_methods' => [
            'Direct user input in the mobile app and authenticated API requests.',
            'Uploaded files and images attached to child records or conversations.',
            'Background processing of uploaded files for search and retrieval features.',
        ],
        'data_usage' => [
            'Provide parenting support, conversation history, and child-specific assistance.',
            'Generate AI responses, summaries, memory extraction, and document search results.',
            'Support appointments, subscriptions, notifications, and account management.',
        ],
        'ai_data_sharing' => [
            'User messages and AI-related uploaded content may be sent to a third-party AI provider after consent is accepted.',
            'AI requests are blocked until the authenticated user accepts AI data-sharing consent.',
        ],
    ],
];

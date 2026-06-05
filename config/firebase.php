<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    'oauth_token_url' => env('FIREBASE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
    'messaging_base_url' => env('FIREBASE_MESSAGING_BASE_URL', 'https://fcm.googleapis.com/v1'),
];

<?php

$parseClientIds = static function (array $values): array {
    $clientIds = [];

    foreach ($values as $value) {
        foreach (explode(',', (string) $value) as $clientId) {
            $normalized = trim($clientId);

            if ($normalized === '') {
                continue;
            }

            $clientIds[$normalized] = true;
        }
    }

    return array_keys($clientIds);
};

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'page_id' => env('FACEBOOK_PAGE_ID'),
    'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
    ],

    'google' => [
        'client_ids' => $parseClientIds([
            env('GOOGLE_CLIENT_IDS'),
            env('GOOGLE_CLIENT_ID'),
        ]),
        'jwks_url' => env('GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
    ],

    'apple' => [
        'client_ids' => $parseClientIds([
            env('APPLE_CLIENT_IDS'),
            env('APPLE_CLIENT_ID'),
            env('APPLE_BUNDLE_ID'),
        ]),
        'jwks_url' => env('APPLE_JWKS_URL', 'https://appleid.apple.com/auth/keys'),
        'issuer' => env('APPLE_ISSUER', 'https://appleid.apple.com'),
    ],

];

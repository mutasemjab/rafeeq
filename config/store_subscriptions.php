<?php

// Google uses product_id or product_id:base_plan_id. The API maps the
// product returned by the store to a plan; the client cannot select a tier.
return [
    'apple' => [
        'bundle' => env('APPLE_STORE_BUNDLE_ID', 'com.rafiq.user'),
        'issuer' => env('APPLE_STORE_ISSUER_ID'),
        'key_id' => env('APPLE_STORE_KEY_ID'),
        'key_path' => env('APPLE_STORE_KEY_PATH'),
        'root_path' => env('APPLE_STORE_ROOT_PEM_PATH'),
        'sandbox' => env('APPLE_STORE_SANDBOX', false),
        'products' => array_filter([
            'pro-monthly' => env('APPLE_STORE_PRO_MONTHLY'),
            'pro-yearly' => env('APPLE_STORE_PRO_YEARLY'),
        ]),
    ],
    'google' => [
        'package' => env('GOOGLE_PLAY_PACKAGE', 'com.rafiq.user'),
        'credentials_path' => env('GOOGLE_PLAY_CREDENTIALS_PATH'),
        'allow_test' => env('GOOGLE_PLAY_ALLOW_TEST', false),
        'products' => array_filter([
            'pro-monthly' => env('GOOGLE_PLAY_PRO_MONTHLY'),
            'pro-yearly' => env('GOOGLE_PLAY_PRO_YEARLY'),
        ]),
    ],
];

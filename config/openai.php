<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | The OpenAI client package reads its credentials from this config file.
    | Keeping it in the repo ensures every environment can resolve the same
    | env variables without requiring a manual vendor config publish step.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
];

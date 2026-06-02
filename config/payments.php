<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Pay Later Toggle
    |--------------------------------------------------------------------------
    |
    | This enables the test-only "pay_for_later" appointment payment method.
    | It is disabled in production by default, but can be enabled explicitly
    | via the PAY_FOR_LATER_ENABLED environment variable when needed.
    |
    */
    'pay_for_later_enabled' => env('PAY_FOR_LATER_ENABLED', env('APP_ENV', 'production') !== 'production'),
];

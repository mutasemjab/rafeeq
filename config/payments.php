<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile Payments Toggle
    |--------------------------------------------------------------------------
    |
    | This controls whether payment options should be available to the mobile
    | app by default. The admin dashboard can override this value at runtime.
    |
    */
    'mobile_enabled' => env('MOBILE_PAYMENTS_ENABLED', true),

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

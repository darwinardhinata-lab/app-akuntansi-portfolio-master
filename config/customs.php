<?php

return [
    'enabled' => env('CEISA_ENABLED', false), // WAJIB false secara default

    'default_environment' => env('CEISA_ENV', 'sandbox'),

    'endpoints' => [
        'sandbox' => env('CEISA_SANDBOX_BASE_URL'),
        'production' => env('CEISA_PRODUCTION_BASE_URL'),
    ],

    'timeout' => env('CEISA_TIMEOUT', 30),

    'retry' => [
        'times' => 3,
        'backoff_seconds' => [10, 60, 300],
    ],

    'signing' => [
        'method' => env('CEISA_SIGN_METHOD', 'hmac'),
    ],

    'polling_interval_minutes' => 15,
];


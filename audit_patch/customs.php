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
        // FIX (T10): key 'api_secret' SEBELUMNYA TIDAK ADA di file ini sama sekali, padahal
        // CeisaSignatureService::signHmac() membaca config('customs.signing.api_secret').
        // Di luar test (yang men-set config secara manual di setUp()), nilai ini akan selalu
        // null -- signing selalu gagal dengan pesan "CEISA_API_SECRET tidak diatur di .env"
        // MESKIPUN variabel .env-nya sudah benar diisi, karena tidak pernah dibaca ke sini.
        'api_secret' => env('CEISA_API_SECRET'),
    ],

    'polling_interval_minutes' => 15,
];


<?php

return [
    'enabled' => env('CEISA_ENABLED', false), // WAJIB false secara default

    'default_environment' => env('CEISA_ENV', 'sandbox'),

    'endpoints' => [
        'sandbox' => env('CEISA_SANDBOX_BASE_URL', 'https://apis-sandbox.beacukai.go.id'),
        'production' => env('CEISA_PRODUCTION_BASE_URL'),
        // [BELUM PASTI - TODO] Path di bawah ini contoh dari spesifikasi yang diberikan,
        // BUKAN konfirmasi final — wajib dicek ulang ke Postman collection resmi DJBC
        // per dokumen (PIB/PEB). Path GET cek status juga masih asumsi (lihat CeisaH2HClient).
        'paths' => [
            'PIB' => env('CEISA_PATH_PIB', '/ceisa/v1/pib'),
            'PEB' => env('CEISA_PATH_PEB', '/ceisa/v1/peb'),
        ],
    ],

    'timeout' => env('CEISA_TIMEOUT', 30),

    'retry' => [
        'times' => 3,
        'backoff_seconds' => [10, 60, 300],
    ],

    'signing' => [
        'method' => env('CEISA_SIGN_METHOD', 'hmac'), // 'hmac' | 'rsa' — 'rsa' masih stub, lihat CeisaSignatureService::signAsymmetric()
        'client_id' => env('CEISA_CLIENT_ID'),
        'client_secret' => env('CEISA_CLIENT_SECRET'),
        // [BELUM PASTI - TODO] Tidak ada alur resmi (client credentials grant?) untuk
        // mendapatkan access_token otomatis di spesifikasi yang diberikan. Untuk sementara
        // token disimpan manual di .env dan TIDAK di-refresh otomatis.
        'access_token' => env('CEISA_ACCESS_TOKEN'),
        'signature_header' => 'beacukai-signature',
        // [BELUM PASTI - TODO] Nama header di bawah ini nama yang wajar berdasarkan pola
        // API sejenis, BUKAN dari dokumentasi resmi — wajib dikonfirmasi ke Postman
        // collection resmi DJBC sebelum dipakai untuk request sungguhan.
        'client_id_header' => 'client-id',
        'timestamp_header' => 'timestamp',
        'authorization_header' => 'Authorization',
    ],

    'polling_interval_minutes' => 15,
];


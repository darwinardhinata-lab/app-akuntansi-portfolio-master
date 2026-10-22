<?php

return [
    'enabled' => env('LIBRETRANSLATE_ENABLED', false),
    // Pilot deliberately accepts loopback endpoints only; never falls back to a public API.
    'url' => env('LIBRETRANSLATE_URL', 'http://127.0.0.1:5001'),
    'source' => env('LIBRETRANSLATE_SOURCE', 'id'),
    'version' => env('LIBRETRANSLATE_MODEL_VERSION', 'pilot-1'),
    'connection' => env('LIBRETRANSLATE_QUEUE_CONNECTION', 'translations'),
    'max_characters' => 5000,
    'targets' => ['en' => 'en', 'zh_CN' => env('LIBRETRANSLATE_CHINESE_TARGET', 'zh-Hans')],
];
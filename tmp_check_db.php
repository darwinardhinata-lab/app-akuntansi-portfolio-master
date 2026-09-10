<?php
// Helper script untuk cek database & user via tinker
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    Illuminate\Console\Input\ArgvInput::class,
    Illuminate\Console\Output\ConsoleOutput::class
);

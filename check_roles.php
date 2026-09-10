<?php
/**
 * Script cek role semua user
 * Jalankan: php check_roles.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Semua User di Database ===\n";
$users = User::all();
foreach ($users as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Name: {$u->name} | Role: [{$u->role}] | Divisi: {$u->id_divisi}\n";
}
echo "\n";

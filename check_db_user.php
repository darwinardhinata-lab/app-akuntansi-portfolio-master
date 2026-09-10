<?php
/**
 * Script cek database & user - jalan di root repo
 * Jalankan: php check_db_user.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Cek Database ===\n";
echo "APP_ENV: " . env('APP_ENV') . "\n";
echo "DB_DATABASE: " . env('DB_DATABASE') . "\n";

try {
    $pdo = DB::connection()->getPdo();
    echo "Koneksi berhasil.\n";
    echo "Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "ERROR KONEKSI: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Cek Tabel ===\n";
$tables = DB::select("SHOW TABLES");
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    echo "Tabel: $name\n";
}

echo "\n=== Cek User ===\n";
$user = DB::table('users')->where('email', 'admin@erp.local')->first();
if ($user) {
    echo "User ditemukan:\n";
    echo "  ID: " . $user->id . "\n";
    echo "  Email: " . $user->email . "\n";
    echo "  Name: " . $user->name . "\n";
    echo "  Password hash: " . substr($user->password, 0, 20) . "...\n";
    echo "  Created: " . $user->created_at . "\n";
} else {
    echo "User 'admin@erp.local' TIDAK ADA di database.\n";
}

$user2 = DB::table('users')->where('email', 'admin@admin.com')->first();
if ($user2) {
    echo "\nUser 'admin@admin.com' ditemukan:\n";
    echo "  ID: " . $user2->id . "\n";
    echo "  Email: " . $user2->email . "\n";
    echo "  Name: " . $user2->name . "\n";
    echo "  Created: " . $user2->created_at . "\n";
} else {
    echo "\nUser 'admin@admin.com' TIDAK ADA di database.\n";
}

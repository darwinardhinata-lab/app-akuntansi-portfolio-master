<?php
/**
 * Script ubah role user jadi ADMIN
 * Jalankan: php set_admin_role.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$emails = ['admin@admin.com', 'admin@erp.local'];

foreach ($emails as $email) {
    $user = User::where('email', $email)->first();
    if ($user) {
        $oldRole = $user->role;
        $user->role = 'ADMIN';
        $user->save();
        echo "User '{$email}' role diubah: [{$oldRole}] → [ADMIN]\n";
    } else {
        echo "User '{$email}' TIDAK DITEMUKAN\n";
    }
}

echo "\n=== Verifikasi: Semua User Sekarang ===\n";
$users = User::all();
foreach ($users as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Name: {$u->name} | Role: [{$u->role}] | Divisi: {$u->id_divisi}\n";
}
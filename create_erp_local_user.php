<?php
/**
 * Script membuat user berdasarkan DatabaseSeeder
 * Jalankan dari root repo: php create_erp_local_user.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::updateOrCreate(
    ['email' => 'admin@erp.local'],
    [
        'name'     => 'Administrator',
        'password' => Hash::make('password'),
    ]
);

echo "User 'admin@erp.local' berhasil dibuat/diupdate:\n";
echo "  ID:      {$user->id}\n";
echo "  Email:   {$user->email}\n";
echo "  Name:    {$user->name}\n";
echo "  Created: {$user->created_at}\n";

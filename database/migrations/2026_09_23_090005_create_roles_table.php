<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian 2: "Adopsi sesi autentikasi; perluas RBAC dan scope perusahaan
     * pada backend." Tabel users.role (string ADMIN/FINANCE/STAFF) yang lama
     * TIDAK dihapus/diubah oleh migration ini - lihat catatan di
     * PlatformFoundationSeeder. roles.code sengaja disamakan dengan nilai
     * lama agar migrasi data user berikutnya tinggal mapping 1:1.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique(); // ADMIN, FINANCE, STAFF, dst
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

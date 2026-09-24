<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 1 Fondasi - Bagian 7 desain penggabungan ERP Marvel + Akuntansi.
     * Tabel companies adalah akar dari seluruh scope multi perusahaan.
     * Additive only: tidak mengubah tabel company_profiles yang sudah ada.
     * Data lama dari company_profiles dipindahkan via seeder, bukan migration ini.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // kode perusahaan pendek, mis. BBW
            $table->string('name', 255);
            $table->char('base_currency', 3)->default('IDR');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            // Tautan opsional ke company_profiles lama agar data legacy (NPWP, alamat) tidak dobel disimpan
            $table->foreignId('legacy_company_profile_id')
                ->nullable()
                ->constrained('company_profiles')
                ->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

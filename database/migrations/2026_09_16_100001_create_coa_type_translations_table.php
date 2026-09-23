<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kamus translation untuk nilai coa_type (BUKAN per-akun).
     *
     * accounts.coa_type adalah varchar(50) free-text (bukan enum tervalidasi),
     * dan nilainya BERULANG di banyak akun (mis. "Fixed Assets" dipakai 11 akun
     * di data COA yang Anda kirim). Kalau translation disimpan per-akun seperti
     * account_translations, string yang sama akan dipanggil ke API translasi
     * berkali-kali dan berisiko hasil translasi tidak konsisten antar akun.
     *
     * Tabel ini menyimpan SATU baris per (nilai coa_type mentah, locale),
     * dipakai sebagai kamus lookup untuk semua akun yang punya coa_type sama.
     *
     * accounts.coa_type sendiri TIDAK diubah -- tetap jadi nilai locale 'id'
     * apa adanya, supaya WHERE coa_type = ... di AccountController/AccountExport
     * (filter & export) tidak perlu disentuh sama sekali.
     */
    public function up(): void
    {
        Schema::create('coa_type_translations', function (Blueprint $table) {
            $table->id();

            $table->string('coa_type');   // nilai mentah kolom accounts.coa_type, apa adanya
            $table->string('locale', 10);
            $table->string('label');

            $table->boolean('is_auto_translated')->default(true);
            $table->timestamp('translated_at')->nullable();

            $table->timestamps();

            $table->unique(['coa_type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_type_translations');
    }
};

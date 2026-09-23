<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel translation untuk account_name.
     * Locale 'id' TIDAK disimpan di sini — accounts.account_name tetap
     * jadi source of truth untuk locale default, supaya semua query/report
     * existing yang baca $account->account_name langsung tidak perlu diubah.
     * Tabel ini hanya menampung OVERRIDE untuk locale non-default (en, zh_CN, dst).
     */
    public function up(): void
    {
        Schema::create('account_translations', function (Blueprint $table) {
            $table->id();

            // FK ke accounts.account_code (bukan integer id — PK accounts adalah string account_code)
            $table->string('account_code');
            $table->string('locale', 10);
            $table->string('name');

            // true = hasil auto-translate via API, belum direview manual.
            // false = sudah diedit manual oleh user -> job auto-translate tidak boleh menimpa lagi.
            $table->boolean('is_auto_translated')->default(true);
            $table->timestamp('translated_at')->nullable();

            $table->timestamps();

            $table->unique(['account_code', 'locale']);

            $table->foreign('account_code')
                ->references('account_code')
                ->on('accounts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_translations');
    }
};

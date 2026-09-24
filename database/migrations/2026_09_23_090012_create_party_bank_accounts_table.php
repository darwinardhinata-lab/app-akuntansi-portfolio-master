<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian 7: "Nomor rekening string, bukan angka." account_no VARCHAR
     * dengan sengaja - jangan pernah diubah ke tipe numerik (angka 0 di
     * depan nomor rekening akan hilang).
     */
    public function up(): void
    {
        Schema::create('party_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('bank_name', 100);
            $table->string('account_no', 100);
            $table->string('account_name', 150);
            $table->char('currency', 3)->default('IDR');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_bank_accounts');
    }
};

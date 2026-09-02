<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('account_code', 20)->primary(); // Kunci utama: Kode Akun (Misal: 110101)
            $table->string('account_name', 100); // Nama Akun (Misal: Kas di Tangan)
            $table->string('coa_type', 50); // Tipe COA (Misal: ASSET)
            $table->enum('normal_balance', ['DEBET', 'KREDIT']); // Pos Saldo
            $table->enum('report_pos', ['NERACA', 'LABA RUGI']); // Pos Laporan
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

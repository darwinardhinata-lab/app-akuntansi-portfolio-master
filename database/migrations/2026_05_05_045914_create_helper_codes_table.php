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
           Schema::create('helper_codes', function (Blueprint $table) {
               $table->string('helper_code', 20)->primary(); // Misal: 110101 (Bisa sama dengan kode akun utama)
               $table->string('entity_name', 100); // Nama Entitas: Ayam Mbok Parmi, dll
               $table->string('marketing_name', 50)->nullable(); // Nama Marketing: Arga
               $table->enum('normal_balance', ['DEBET', 'KREDIT']);
               $table->timestamps();
           });
       }
       
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helper_codes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_details', function (Blueprint $table) {
            // Primary Key detail dari Jubelio
            $table->integer('journal_detail_id')->primary(); 
            
            // Foreign Key yang menghubungkan ke tabel journal_headers
            $table->string('journal_id', 50)->nullable();       
            
            $table->string('account_code', 50)->nullable();
            $table->string('account_name', 250)->nullable();
            $table->text('description')->nullable();
            
            // Nilai debet & kredit per baris akun
            $table->float('debit', 16, 4)->nullable();
            $table->float('credit', 16, 4)->nullable();
            
            $table->timestamps();

            // Membuat index agar laporan Buku Besar & Laba Rugi berjalan secepat kilat
            $table->index('journal_id');
            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_details');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_details', function (Blueprint $table) {
            // Auto-increment primary key
            $table->id();

            // Foreign Key yang menghubungkan ke tabel journal_headers
            $table->string('journal_id', 50)->nullable();

            $table->string('account_code', 50)->nullable();
            $table->string('account_name', 250)->nullable();
            $table->unsignedInteger('account_id')->nullable();
            $table->text('description')->nullable();

            // Position: 'debit' or 'credit'
            $table->string('position', 10)->nullable();

            // Amount for this journal line
            $table->float('amount', 16, 4)->nullable();

            // Journal number reference
            $table->string('journal_no', 150)->nullable();

            // Nilai debet & kredit per baris akun (legacy)
            $table->float('debit', 16, 4)->nullable();
            $table->float('credit', 16, 4)->nullable();

            // Helper code
            $table->string('helper_code', 50)->nullable();

            $table->timestamps();

            // Index untuk performa
            $table->index('journal_id');
            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_details');
    }
};
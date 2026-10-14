<?php

// FIX: multicurrency — master aditif; tidak mengubah tabel jurnal atau dokumen.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cur_currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimal_places')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('show')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cur_currencies');
    }
};
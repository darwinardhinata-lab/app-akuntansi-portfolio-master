<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbr_dokumen_pabean_lines', function (Blueprint $table) {
            $table->date('tgl_dok_pabean')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Retain nullable dates: an unknown customs date must never be fabricated on rollback.
    }
};
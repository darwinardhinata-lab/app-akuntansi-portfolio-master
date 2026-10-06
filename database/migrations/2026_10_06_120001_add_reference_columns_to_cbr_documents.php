<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbr_dokumen_pabean_lines', function (Blueprint $table) {
            $table->string('no_aju', 100)->nullable();
            $table->decimal('bruto', 20, 4)->nullable();
            $table->decimal('netto', 20, 4)->nullable();
            $table->decimal('harga_idr', 20, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cbr_dokumen_pabean_lines', function (Blueprint $table) {
            $table->dropColumn(['no_aju', 'bruto', 'netto', 'harga_idr']);
        });
    }
};
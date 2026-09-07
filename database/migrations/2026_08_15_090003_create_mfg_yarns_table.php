<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_yarns
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_yarns', function (Blueprint $table) {
            $table->id();
            $table->string('yarn_code', 50)->unique();
            $table->string('yarn_type', 100);
            $table->string('yarn_count', 50)->nullable();
            $table->string('composition', 255)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('unit', 20)->default('KGS');
            $table->decimal('stock_quantity', 20, 2)->default(0);
            $table->decimal('average_cost', 20, 2)->default(0);
            $table->string('inventory_account_code', 50)->default('11210'); // Persediaan Bahan Baku - Yarn
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_yarns');
    }
};

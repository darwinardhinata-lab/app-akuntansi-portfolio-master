<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_fabrics
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_fabrics', function (Blueprint $table) {
            $table->id();
            $table->string('fabric_code', 50)->unique();
            $table->string('fabric_type', 50);
            $table->string('subtype', 100)->nullable();
            $table->enum('state', ['GREY','FINISHED'])->default('FINISHED');
            $table->integer('gsm')->nullable();
            $table->string('composition', 255)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('unit', 20)->default('KGS');
            $table->decimal('stock_quantity', 20, 2)->default(0);
            $table->decimal('average_cost', 20, 2)->default(0);
            $table->string('inventory_account_code', 50)->default('11220'); // Persediaan Bahan Baku - Kain
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_fabrics');
    }
};

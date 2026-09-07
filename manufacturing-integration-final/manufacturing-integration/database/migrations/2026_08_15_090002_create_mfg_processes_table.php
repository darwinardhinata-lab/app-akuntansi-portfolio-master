<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_processes
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_processes', function (Blueprint $table) {
            $table->id();
            $table->enum('process_type', ['KNITTING','DYEING','PRINTING','FINISHING','CUTTING','STITCHING','OTHER']);
            $table->string('process_name', 150);
            $table->enum('rate_unit', ['PER_KG','PER_PCS'])->default('PER_KG');
            $table->decimal('process_rate', 20, 2)->default(0);
            $table->unsignedBigInteger('default_supplier_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('default_supplier_id')->references('id')->on('mfg_suppliers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_processes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_suppliers
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 50)->unique();
            $table->string('supplier_name', 255);
            $table->enum('supplier_type', ['RAW_MATERIAL','KNITTER','PROCESSOR','CUTTING','STITCHER','FINISHING','OTHER'])->default('OTHER');
            $table->string('helper_code', 50)->nullable()->comment('FK ke helper_codes.helper_code utk subledger AP jasa maklun/pembelian bahan baku');
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('payment_terms')->nullable();
            $table->integer('credit_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('helper_code')->references('helper_code')->on('helper_codes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_suppliers');
    }
};

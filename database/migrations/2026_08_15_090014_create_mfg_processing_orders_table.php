<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_processing_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_processing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique(); // Format: PRC-YYYYMMDD-####
            $table->date('order_date');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('supplier_id'); // Processor/dyer
            $table->enum('process_type', ['DYEING','PRINTING','FINISHING']);
            $table->enum('status', ['OPEN','ISSUED','PARTIAL_RECEIVED','COMPLETED','CANCELED'])->default('OPEN');
            $table->date('target_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('mfg_suppliers')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_processing_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_fabric_receipts
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_fabric_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique(); // Format: FR-YYYYMMDD-####
            $table->date('receipt_date');
            $table->unsignedBigInteger('processing_order_id');
            $table->unsignedBigInteger('fabric_id'); // kain finished (state=FINISHED) - hasil konversi dari grey
            $table->decimal('qty_received', 20, 2);
            $table->decimal('qty_rejected', 20, 2)->default(0);
            $table->string('lot_number', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('shade_code', 50)->nullable();
            $table->decimal('shrinkage_percent', 5, 2)->default(0);
            $table->decimal('process_cost_amount', 20, 2)->default(0)->comment('Tagihan jasa proses (dyeing/printing/finishing) dari vendor utk batch ini');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('processing_order_id')->references('id')->on('mfg_processing_orders')->onDelete('cascade');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_fabric_receipts');
    }
};

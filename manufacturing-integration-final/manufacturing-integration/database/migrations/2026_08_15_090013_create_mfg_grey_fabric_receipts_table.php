<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_grey_fabric_receipts
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_grey_fabric_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique(); // Format: GFR-YYYYMMDD-####
            $table->date('receipt_date');
            $table->unsignedBigInteger('knit_order_id');
            $table->unsignedBigInteger('fabric_id');
            $table->decimal('qty_received', 20, 2);
            $table->decimal('qty_rejected', 20, 2)->default(0);
            $table->string('lot_number', 50)->nullable();
            $table->integer('gsm_actual')->nullable();
            $table->decimal('knitting_cost_amount', 20, 2)->default(0)->comment('Biaya jasa knitting utk batch ini (dari rate proses x qty)');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('knit_order_id')->references('id')->on('mfg_knit_orders')->onDelete('cascade');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_grey_fabric_receipts');
    }
};

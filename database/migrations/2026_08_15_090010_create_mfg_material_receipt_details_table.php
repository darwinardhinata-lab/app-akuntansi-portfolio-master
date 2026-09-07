<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_material_receipt_details
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_receipt_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('po_detail_id')->nullable();
            $table->enum('item_type', ['YARN','FABRIC']);
            $table->unsignedBigInteger('yarn_id')->nullable();
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->string('item_name', 255);
            $table->decimal('qty', 20, 2);
            $table->string('unit', 20)->default('KGS');
            $table->decimal('rate', 20, 2);
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('lot_number', 50)->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('mfg_material_receipts')->onDelete('cascade');
            $table->foreign('po_detail_id')->references('id')->on('mfg_material_purchase_order_details')->onDelete('set null');
            $table->foreign('yarn_id')->references('id')->on('mfg_yarns')->onDelete('set null');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_receipt_details');
    }
};

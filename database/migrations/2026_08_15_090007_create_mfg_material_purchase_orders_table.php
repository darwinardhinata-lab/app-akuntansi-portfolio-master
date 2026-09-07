<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_material_purchase_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique(); // Format: MFGPO-YYYYMMDD-####
            $table->date('po_date');
            $table->unsignedBigInteger('supplier_id');
            $table->enum('status', ['DRAFT','APPROVED','PARTIAL','RECEIVED','CANCELED'])->default('APPROVED');
            $table->decimal('sub_total', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('mfg_suppliers')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_purchase_orders');
    }
};

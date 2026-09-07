<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_material_receipts
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique(); // Format: MRN-YYYYMMDD-####
            $table->date('receipt_date');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('po_id')->nullable();
            $table->string('supplier_doc_no', 100)->nullable();
            $table->date('supplier_doc_date')->nullable();
            $table->decimal('gross_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('net_amount', 20, 2)->default(0);
            $table->enum('status', ['DRAFT','POSTED'])->default('DRAFT');
            $table->string('journal_id', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('mfg_suppliers')->onDelete('restrict');
            $table->foreign('po_id')->references('id')->on('mfg_material_purchase_orders')->onDelete('set null');
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_receipts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_fabric_issues
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_fabric_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number', 50)->unique(); // Format: FI-YYYYMMDD-####
            $table->date('issue_date');
            $table->unsignedBigInteger('processing_order_id');
            $table->unsignedBigInteger('fabric_id'); // kain grey (state=GREY)
            $table->decimal('qty_issued', 20, 2);
            $table->decimal('unit_cost', 20, 2)->default(0);
            $table->decimal('total_cost', 20, 2)->default(0);
            $table->string('lot_number', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->timestamps();

            $table->foreign('processing_order_id')->references('id')->on('mfg_processing_orders')->onDelete('cascade');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_fabric_issues');
    }
};

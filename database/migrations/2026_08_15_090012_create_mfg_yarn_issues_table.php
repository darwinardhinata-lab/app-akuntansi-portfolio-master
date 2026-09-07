<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_yarn_issues
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_yarn_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number', 50)->unique(); // Format: YI-YYYYMMDD-####
            $table->date('issue_date');
            $table->unsignedBigInteger('knit_order_id');
            $table->unsignedBigInteger('yarn_id');
            $table->string('lot_number', 50)->nullable();
            $table->decimal('qty_issued', 20, 2);
            $table->decimal('unit_cost', 20, 2)->default(0); // snapshot moving average saat issue
            $table->decimal('total_cost', 20, 2)->default(0);
            $table->decimal('returned_qty', 20, 2)->default(0);
            $table->timestamps();

            $table->foreign('knit_order_id')->references('id')->on('mfg_knit_orders')->onDelete('cascade');
            $table->foreign('yarn_id')->references('id')->on('mfg_yarns')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_yarn_issues');
    }
};

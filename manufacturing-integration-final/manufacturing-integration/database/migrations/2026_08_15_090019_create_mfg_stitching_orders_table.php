<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_stitching_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_stitching_orders', function (Blueprint $table) {
            $table->id();
            $table->string('stitching_order_number', 50)->unique(); // Format: SEW-YYYYMMDD-####
            $table->date('order_date');
            $table->unsignedBigInteger('cutting_order_id');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('supplier_id')->nullable(); // Stitcher/CMT vendor
            $table->integer('pieces_issued');
            $table->json('size_breakdown')->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['OPEN','ISSUED','RECEIVED','COMPLETED','CANCELED'])->default('OPEN');
            $table->decimal('stitching_rate', 20, 2)->nullable()->comment('Rate per pcs (dari mfg_processes / kontrak vendor)');
            $table->decimal('total_stitching_cost', 20, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('cutting_order_id')->references('id')->on('mfg_cutting_orders')->onDelete('restrict');
            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('mfg_suppliers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_stitching_orders');
    }
};

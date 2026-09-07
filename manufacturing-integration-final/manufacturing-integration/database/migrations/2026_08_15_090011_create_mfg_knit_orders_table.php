<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_knit_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_knit_orders', function (Blueprint $table) {
            $table->id();
            $table->string('knit_order_number', 50)->unique(); // Format: KO-YYYYMMDD-####
            $table->date('order_date');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('supplier_id'); // Knitter
            $table->unsignedBigInteger('fabric_id'); // Target kain grey yg dihasilkan
            $table->decimal('planned_qty_kg', 20, 2);
            $table->enum('status', ['OPEN','ISSUED','PARTIAL_RECEIVED','COMPLETED','CANCELED'])->default('OPEN');
            $table->date('target_date')->nullable();
            $table->integer('gsm')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('mfg_suppliers')->onDelete('restrict');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_knit_orders');
    }
};

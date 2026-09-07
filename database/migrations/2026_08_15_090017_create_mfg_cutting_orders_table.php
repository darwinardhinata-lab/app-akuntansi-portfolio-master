<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_cutting_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_cutting_orders', function (Blueprint $table) {
            $table->id();
            $table->string('cutting_order_number', 50)->unique(); // Format: CO-YYYYMMDD-####
            $table->date('order_date');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('fabric_id'); // kain finished yg dipotong
            $table->decimal('fabric_qty_issued', 20, 2);
            $table->decimal('fabric_unit_cost', 20, 2)->default(0);
            $table->decimal('fabric_total_cost', 20, 2)->default(0);
            $table->integer('planned_pieces');
            $table->json('size_breakdown')->nullable();
            $table->decimal('marker_efficiency', 5, 2)->nullable();
            $table->enum('status', ['OPEN','CHECKED','COMPLETED','CANCELED'])->default('OPEN');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('cascade');
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_cutting_orders');
    }
};

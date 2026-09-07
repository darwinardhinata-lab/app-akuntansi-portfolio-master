<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_cutting_checks
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_cutting_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutting_order_id');
            $table->date('check_date');
            $table->integer('pieces_cut');
            $table->integer('pieces_ok');
            $table->integer('pieces_rejected')->default(0);
            $table->decimal('fabric_used_kg', 20, 2)->nullable();
            $table->decimal('fabric_wastage_kg', 20, 2)->nullable();
            $table->decimal('wastage_cost_amount', 20, 2)->default(0)->comment('Nilai kerugian wastage kain = fabric_wastage_kg x fabric_unit_cost');
            $table->json('size_breakdown_actual')->nullable();
            $table->string('checked_by', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('cutting_order_id')->references('id')->on('mfg_cutting_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_cutting_checks');
    }
};

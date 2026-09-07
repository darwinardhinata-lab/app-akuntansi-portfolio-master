<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_finishing_stages
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_finishing_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stitching_order_id');
            $table->unsignedBigInteger('work_order_id');
            $table->enum('stage', ['WASHING','IRONING','QC','PACKING','OTHER']);
            $table->date('stage_date');
            $table->integer('pieces_in');
            $table->integer('pieces_ok');
            $table->integer('pieces_rejected')->default(0);
            $table->json('size_breakdown')->nullable();
            $table->string('operator', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('stitching_order_id')->references('id')->on('mfg_stitching_orders')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_finishing_stages');
    }
};

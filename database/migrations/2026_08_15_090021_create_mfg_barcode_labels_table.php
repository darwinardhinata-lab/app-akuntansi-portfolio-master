<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_barcode_labels
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_barcode_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('finishing_stage_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('size', 20);
            $table->string('barcode', 100)->unique();
            $table->decimal('mrp', 20, 2);
            $table->string('batch_number', 50)->nullable();
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('mfg_work_orders')->onDelete('cascade');
            $table->foreign('finishing_stage_id')->references('id')->on('mfg_finishing_stages')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_barcode_labels');
    }
};

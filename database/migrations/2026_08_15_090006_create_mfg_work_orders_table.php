<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_work_orders
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('spk_number', 50)->unique(); // Format: MFG-YYYYMMDD-####
            $table->date('order_date');
            $table->unsignedBigInteger('product_id')->nullable()->comment('FK ke products (SKU barang jadi tujuan)');
            $table->string('style_sku', 100)->nullable();
            $table->string('garment_name', 255)->nullable();
            $table->integer('planned_qty')->default(0);
            $table->json('size_breakdown')->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['DRAFT','CUTTING','STITCHING','FINISHING','COMPLETED','CANCELED'])->default('DRAFT');
            $table->string('wip_account_code', 50)->default('11500'); // Persediaan Barang Dalam Proses (WIP)
            $table->decimal('total_material_cost', 20, 2)->default(0);
            $table->decimal('total_process_cost', 20, 2)->default(0);
            $table->decimal('total_wip_cost', 20, 2)->default(0);
            $table->string('journal_id', 50)->nullable()->comment('FK ke journal_headers.journal_id - jurnal penyelesaian SPK (WIP -> Barang Jadi)');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_work_orders');
    }
};

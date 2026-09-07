<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Manufaktur (MFG/SPK) — mfg_material_ledgers
 * Bagian dari integrasi Anthrilo Manufacturing -> ERP Akuntansi.
 * Mengikuti konvensi RULES.md: $table->id(), decimal(20,2) utk nominal,
 * timestamps, foreign() eksplisit, prefix tabel `mfg_` agar terisolasi
 * dari core accounting (App\Modules\Manufacturing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('evidence_number', 100);
            $table->enum('item_type', ['YARN','FABRIC']);
            $table->unsignedBigInteger('item_id'); // yarn_id atau fabric_id (polymorphic ringan sesuai item_type)
            $table->enum('type', ['IN','OUT','ADJ']);
            $table->decimal('qty', 20, 2);
            $table->decimal('unit_cost', 20, 2);
            $table->decimal('total_cost', 20, 2);
            $table->decimal('running_qty', 20, 2);
            $table->decimal('running_value', 20, 2);
            $table->decimal('moving_average_cost', 20, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_ledgers');
    }
};

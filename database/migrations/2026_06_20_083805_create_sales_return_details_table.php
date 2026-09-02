<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_return_details', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            
            $table->string('item_code', 100);
            $table->string('description', 255)->nullable();
            
            // Kuantitas
            $table->integer('qty_returned')->default(0);
            $table->integer('qty_approved')->default(0);
            
            // Kondisi Barang (GOOD = Masuk stok utama, DEFECTIVE = Masuk gudang afval/cacat)
            $table->string('condition', 20)->default('GOOD');
            
            // Jaring Pengaman Akuntansi (Penguncian Nilai Historis)
            $table->decimal('unit_price', 20, 2)->default(0);
            $table->decimal('prorated_discount', 20, 2)->default(0);
            $table->decimal('cogs_value', 20, 2)->default(0);
            
            $table->decimal('subtotal_refund', 20, 2)->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_details');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique(); // Item Code Jubelio
            $table->string('name');
            $table->string('variation')->nullable();
            $table->string('category_name')->nullable();
            
            // Satuan default
            $table->string('unit', 20)->default('Pcs'); 
            
            // Harga & Stok
            $table->decimal('sell_price', 20, 2)->default(0); // Harga Jual
            $table->decimal('average_cost', 20, 2)->default(0); // HPP Jubelio (Moving Average)
            $table->integer('stock_quantity')->default(0); // Tersedia
            
            // Pemetaan Jurnal Otomatis COGS & Inventory
            $table->string('inventory_account_code', 50)->default('11200'); // Akun Persediaan
            $table->string('cogs_account_code', 50)->default('55000'); // Akun HPP
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
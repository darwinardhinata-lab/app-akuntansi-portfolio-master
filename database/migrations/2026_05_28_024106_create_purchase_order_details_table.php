<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id')->nullable(); // Terhubung ke tabel Product
            
            $table->string('item_code', 100); // Raw SKU dari CSV
            $table->string('description')->nullable();
            
            // Angka-angka dari Jubelio
            $table->decimal('price', 20, 2)->default(0);
            $table->integer('qty');
            $table->decimal('disc_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('amount', 20, 2)->default(0); // Total per baris

            // Pelacakan Penerimaan Barang Gudang
            $table->integer('qty_received')->default(0); 

            $table->timestamps();

            // Relasi
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_details');
    }
};
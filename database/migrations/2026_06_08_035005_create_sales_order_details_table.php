<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_code', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('price', 20, 2)->default(0);
            $table->integer('qty')->default(0);
            $table->integer('qty_shipped')->default(0);
            $table->decimal('disc_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_details');
    }
};
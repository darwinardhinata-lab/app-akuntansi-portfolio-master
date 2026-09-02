<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_number', 100)->unique();
            $table->date('transaction_date');
            $table->string('contact_name', 150);
            $table->string('location_name', 100)->nullable();
            $table->string('status', 30)->default('APPROVED');
            $table->decimal('sub_total', 20, 2)->default(0);
            $table->decimal('disc_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
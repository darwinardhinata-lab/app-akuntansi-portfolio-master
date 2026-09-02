<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SELF-HEALING: Hapus sisa tabel jika sebelumnya nyangkut/error
        Schema::dropIfExists('sales_invoices');

        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100)->unique();
            
            // SYNTAX MODERN: Lebih aman dari error 150
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            
            $table->date('transaction_date');
            $table->string('contact_name', 150);
            
            $table->decimal('sub_total', 20, 2)->default(0);
            $table->decimal('disc_amount', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('shipping_cost', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            
            $table->string('payment_status', 30)->default('UNPAID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
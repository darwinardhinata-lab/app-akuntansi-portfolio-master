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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 100)->unique();
            
            // Relasi ke Faktur Penjualan (Sumber Retur)
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->restrictOnDelete();
            
            $table->date('return_date');
            
            // Status: PENDING_INSPECTION, APPROVED, REJECTED, FAILED_DELIVERY
            $table->string('status', 30)->default('PENDING_INSPECTION');
            
            // Kolom Audit Gudang
            $table->string('inspected_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            
            // Komponen Pengembalian Dana (Jaring Pengaman Finansial)
            $table->decimal('refund_shipping_cost', 20, 2)->default(0);
            $table->decimal('return_shipping_cost', 20, 2)->default(0);
            $table->decimal('total_refund_amount', 20, 2)->default(0);
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
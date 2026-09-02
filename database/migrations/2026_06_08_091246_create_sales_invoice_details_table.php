<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SELF-HEALING: Membersihkan wujud tabel yang terbuat setengah jalan akibat error sebelumnya
        Schema::dropIfExists('sales_invoice_details');

        Schema::create('sales_invoice_details', function (Blueprint $table) {
            $table->id();
            
            // SYNTAX MODERN: Otomatis mendeteksi tipe data tabel induk agar presisi 100%
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            
            $table->string('item_code', 100);
            $table->string('description', 255)->nullable();
            
            $table->decimal('price', 20, 2)->default(0);
            $table->integer('qty_actual')->default(0); 
            $table->decimal('disc_amount', 20, 2)->default(0);
            $table->decimal('amount', 20, 2)->default(0);
            
            $table->boolean('is_substitution')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_details');
    }
};
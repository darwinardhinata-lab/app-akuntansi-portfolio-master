<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_headers', function (Blueprint $table) {
            // Primary Key menggunakan journal_id asli dari Jubelio (Contoh: 1399694)
            $table->string('journal_id', 50)->primary(); 
            $table->string('journal_no', 150)->nullable();
            $table->datetime('transaction_date')->nullable();
            $table->string('source_doc_no', 150)->nullable();
            
            // Kolom tracking asal dokumen pelacak dari Jubelio (Bisa null)
            $table->integer('payment_id')->nullable();
            $table->integer('invoice_id')->nullable();
            $table->integer('bill_id')->nullable();
            $table->integer('sales_ret_id')->nullable();
            $table->integer('purch_ret_id')->nullable();
            $table->integer('item_adj_id')->nullable();
            
            $table->boolean('is_opening_balance')->default(false);
            
            // Total balancing jurnal di level header, disamakan tipe float(16,4) bawaan Anda
            $table->float('debit', 16, 4)->nullable();
            $table->float('credit', 16, 4)->nullable();
            
            $table->text('notes')->nullable();
            $table->string('journal_type', 100)->nullable();
            $table->string('transaction_type', 100)->nullable();
            
            // Untuk menampung string nama toko/channel (Contoh: "Shop | Tokopedia - KidsmateOfficial")
            $table->string('tags', 255)->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_headers');
    }
};
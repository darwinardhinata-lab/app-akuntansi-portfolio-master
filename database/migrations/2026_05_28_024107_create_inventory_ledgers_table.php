<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('evidence_number', 100); // No Bukti Penerimaan Barang / No Invoice
            
            $table->unsignedBigInteger('product_id');
            
            // Tipe Transaksi: IN (Masuk), OUT (Keluar), ADJ (Penyesuaian)
            $table->enum('type', ['IN', 'OUT', 'ADJ']); 
            
            $table->integer('qty'); // Jumlah mutasi
            $table->decimal('unit_cost', 20, 2); // Harga modal per Pcs saat transaksi ini
            $table->decimal('total_cost', 20, 2); // QTY x Unit Cost
            
            // Saldo Kumulatif HPP (Untuk Laporan Nilai Persediaan)
            $table->integer('running_qty'); 
            $table->decimal('running_value', 20, 2); 
            $table->decimal('moving_average_cost', 20, 2); // HPP Baru

            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_ledgers');
    }
};
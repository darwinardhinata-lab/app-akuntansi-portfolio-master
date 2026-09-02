<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransaksiPaymentPlanTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('transaksi_payment_plan');

        Schema::create('transaksi_payment_plan', function (Blueprint $table) {
            $table->id('id_payment');
            $table->string('no_transaksi', 50)->unique(); 
            
            // Relasi ke Master Divisi
            $table->unsignedBigInteger('id_divisi'); 
            
            $table->date('tgl_pengajuan');
            $table->date('jatuh_tempo')->nullable(); // Bisa kosong jika Kas Kecil
            
            $table->string('jenis_transaksi', 30); // KAS KECIL / KAS BESAR / BANK
            $table->string('vendor_toko', 100)->nullable();
            $table->string('penerima_pj', 100);
            $table->string('rekening_va', 150)->nullable();
            
            $table->text('keterangan');
            $table->decimal('nominal', 15, 2);
            
            $table->string('status_payment', 30)->default('PENGAJUAN'); // PENGAJUAN / APPROVED / PAID
            
            $table->timestamps();

            // Foreign Key (Mengunci agar tidak ada transaksi dengan divisi bodong)
            $table->foreign('id_divisi')->references('id_divisi')->on('master_divisi')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaksi_payment_plan');
    }
}
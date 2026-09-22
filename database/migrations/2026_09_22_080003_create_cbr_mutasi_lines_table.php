<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbr_mutasi_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_period_id');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan_barang', 20);
            $table->decimal('jumlah_barang', 20, 2);
            $table->decimal('saldo_awal', 20, 2);
            $table->decimal('jumlah_pemasukan_barang', 20, 2);
            $table->decimal('jumlah_pengeluaran_barang', 20, 2);
            $table->decimal('penyesuaian_adjustment', 20, 2);
            $table->decimal('saldo_akhir', 20, 2);
            $table->string('hasil_pencacahan', 20)->comment('Belum|Sudah');
            $table->decimal('jumlah_selisih', 20, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('report_period_id')
                ->references('id')
                ->on('cbr_report_periods')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbr_mutasi_lines');
    }
};
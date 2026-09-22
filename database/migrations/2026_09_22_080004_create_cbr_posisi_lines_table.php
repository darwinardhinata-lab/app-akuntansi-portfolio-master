<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbr_posisi_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_period_id');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan_barang', 20);
            $table->decimal('jumlah_barang', 20, 2);
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
        Schema::dropIfExists('cbr_posisi_lines');
    }
};
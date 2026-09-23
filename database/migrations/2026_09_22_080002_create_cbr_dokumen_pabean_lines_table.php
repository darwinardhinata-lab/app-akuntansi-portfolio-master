<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbr_dokumen_pabean_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_period_id');
            $table->string('jenis_dok_pabean');
            $table->string('no_pendaftaran_dok_pabean');
            $table->date('tgl_dok_pabean');
            $table->string('no_bukti');
            $table->date('tgl_bukti');
            $table->string('pihak_terkait');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->decimal('jumlah_barang', 20, 2);
            $table->string('satuan_barang', 20);
            $table->string('mata_uang', 3);
            $table->decimal('nilai', 20, 4);
            $table->string('seri_faktur_pajak', 50)->nullable();
            $table->decimal('nilai_faktur_pajak', 20, 4)->nullable();
            $table->timestamps();

            $table->foreign('report_period_id')
                ->references('id')
                ->on('cbr_report_periods')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbr_dokumen_pabean_lines');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbr_report_periods', function (Blueprint $table) {
            $table->id();
            $table->string('report_type')->comment('PEMASUKAN|PENGELUARAN|MUTASI_BAHAN_BAKU|WIP|MUTASI_BARANG_JADI|MUTASI_BARANG_MODAL|MUTASI_REJECT');
            $table->unsignedTinyInteger('periode_bulan');
            $table->unsignedSmallInteger('periode_tahun');
            $table->string('status')->default('DRAFT')->comment('DRAFT|FINAL|DIUNGGAH');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['report_type', 'periode_bulan', 'periode_tahun']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbr_report_periods');
    }
};
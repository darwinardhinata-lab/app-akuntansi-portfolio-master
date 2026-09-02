<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat journal_detail_id nullable agar aset yang diimport secara manual
     * (tanpa jurnal terkait) dapat tersimpan dengan benar.
     */
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            // Ubah kolom menjadi nullable — aset manual tidak memiliki journal_detail_id
            $table->unsignedBigInteger('journal_detail_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            // Catatan: rollback ini akan gagal jika ada baris dengan journal_detail_id = NULL
            $table->unsignedBigInteger('journal_detail_id')->nullable(false)->change();
        });
    }
};

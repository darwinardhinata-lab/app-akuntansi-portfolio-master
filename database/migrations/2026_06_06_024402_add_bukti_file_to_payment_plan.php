<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            // Menambahkan kolom bukti_file setelah keterangan
            $table->string('bukti_file')->nullable()->after('keterangan');
        });
    }

    public function down()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn('bukti_file');
        });
    }
};
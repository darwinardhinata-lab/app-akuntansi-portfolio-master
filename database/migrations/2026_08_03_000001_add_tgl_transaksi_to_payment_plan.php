<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->date('tgl_transaksi')->nullable()->after('tgl_pengajuan');
        });
    }

    public function down()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn('tgl_transaksi');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKategoriPaymentToPaymentPlan extends Migration
{
    public function up()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            // Menambah kolom kategori_payment
            $table->string('kategori_payment')->nullable()->after('jenis_transaksi');
        });
    }

    public function down()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn('kategori_payment');
        });
    }
}
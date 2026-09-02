<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdAkunToTransaksiPaymentPlan extends Migration
{
    public function up()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            // Menambahkan kolom id_akun yang boleh kosong (nullable)
            // Tipe string digunakan agar bisa menampung kode akun dari tabel accounts
            $table->string('id_akun', 50)->nullable()->after('id_divisi');
        });
    }

    public function down()
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn('id_akun');
        });
    }
}
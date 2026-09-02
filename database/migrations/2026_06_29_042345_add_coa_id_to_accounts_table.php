<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom coa_id (Jubelio Chart of Account ID) ke tabel accounts
     * agar mapping antara ID akun Jubelio (Integer) dan Kode Akun ERP (String) 
     * dapat dilakukan dengan benar selama proses sinkronisasi ETL di ProcessPendingTempJob.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->bigInteger('coa_id')->nullable()->unique()->after('account_code')
                  ->comment('Jubelio Chart of Account ID (integer) untuk mapping ETL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('coa_id');
        });
    }
};
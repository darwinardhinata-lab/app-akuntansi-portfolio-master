<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->string('nama_toko_link', 150)->nullable()->after('vendor_toko');
            $table->decimal('qty', 10, 2)->nullable()->default(1)->after('rekening_va');
            $table->string('satuan', 20)->nullable()->default('Pcs')->after('qty');
            $table->decimal('nominal_aktual', 15, 2)->nullable()->after('nominal');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn(['nama_toko_link', 'qty', 'satuan', 'nominal_aktual']);
        });
    }
};
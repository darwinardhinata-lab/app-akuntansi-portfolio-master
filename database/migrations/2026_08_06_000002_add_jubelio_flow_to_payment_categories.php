<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->string('jubelio_flow', 20)->default('KAS_BANK')->after('description'); // KAS_BANK | MANUAL_HUTANG
            $table->string('tipe_kas_bank', 20)->nullable()->after('jubelio_flow'); // PEMBAYARAN | PENERIMAAN
        });

        DB::table('payment_categories')->where('name', 'PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)')
            ->update(['jubelio_flow' => 'MANUAL_HUTANG', 'tipe_kas_bank' => null]);
        DB::table('payment_categories')->where('name', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')
            ->update(['jubelio_flow' => 'MANUAL_HUTANG', 'tipe_kas_bank' => null]);
        DB::table('payment_categories')->where('name', 'PEMBELIAN & OPERASIONAL')
            ->update(['jubelio_flow' => 'KAS_BANK', 'tipe_kas_bank' => 'PEMBAYARAN']);
        DB::table('payment_categories')->where('name', 'ASET')
            ->update(['jubelio_flow' => 'KAS_BANK', 'tipe_kas_bank' => 'PEMBAYARAN']);
        DB::table('payment_categories')->where('name', 'PRIVE')
            ->update(['jubelio_flow' => 'KAS_BANK', 'tipe_kas_bank' => 'PEMBAYARAN']);
        DB::table('payment_categories')->where('name', 'PB')
            ->update(['jubelio_flow' => 'KAS_BANK', 'tipe_kas_bank' => 'PENERIMAAN']);

        if (!DB::table('payment_categories')->where('name', 'DEPOSIT')->exists()) {
            DB::table('payment_categories')->insert([
                'name' => 'DEPOSIT',
                'slug' => 'deposit',
                'description' => 'Pembayaran deposit (harus diinput manual di menu Hutang Jubelio)',
                'is_active' => true,
                'jubelio_flow' => 'MANUAL_HUTANG',
                'tipe_kas_bank' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->dropColumn(['jubelio_flow', 'tipe_kas_bank']);
        });
    }
};
<?php

// FIX: multicurrency — NULL mempertahankan mata uang dasar tanpa mengisi data COA.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', fn (Blueprint $table) => $table->char('currency_code', 3)->nullable());
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('accounts')->whereNotNull('currency_code')->exists()) {
            throw new \RuntimeException('Rollback ditolak: mata uang akun masih ditetapkan.');
        }
        Schema::table('accounts', fn (Blueprint $table) => $table->dropColumn('currency_code'));
    }
};
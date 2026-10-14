<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cur_exchange_rates', fn (Blueprint $table) => $table->timestamp('first_used_at')->nullable());
    }

    public function down(): void
    {
        if (DB::table('cur_exchange_rates')->whereNotNull('first_used_at')->exists()) {
            throw new \RuntimeException('Rollback ditolak: kurs sudah dipakai oleh posting.');
        }
        Schema::table('cur_exchange_rates', fn (Blueprint $table) => $table->dropColumn('first_used_at'));
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cur_exchange_rates', function (Blueprint $table) {
            $table->unsignedInteger('quoted_unit')->default(1);
            $table->decimal('quoted_rate', 20, 8)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('cur_exchange_rates')->whereNotNull('quoted_rate')->exists()) {
            throw new RuntimeException('Rollback ditolak: kuotasi kurs asli masih tersedia.');
        }
        Schema::table('cur_exchange_rates', fn (Blueprint $table) => $table->dropColumn(['quoted_unit', 'quoted_rate']));
    }
};
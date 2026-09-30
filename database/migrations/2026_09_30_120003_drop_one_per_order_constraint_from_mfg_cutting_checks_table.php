<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_cutting_checks', function (Blueprint $table) {
            $table->dropUnique('mfg_cutting_check_one_per_order');
        });
    }

    public function down(): void
    {
        Schema::table('mfg_cutting_checks', function (Blueprint $table) {
            $table->unique('cutting_order_id', 'mfg_cutting_check_one_per_order');
        });
    }
};
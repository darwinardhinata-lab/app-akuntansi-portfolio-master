<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL needs a replacement supporting index before dropping the FK's unique index.
        if (!Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_checks_order_lookup')) {
            Schema::table('mfg_cutting_checks', function (Blueprint $table) {
                $table->index('cutting_order_id', 'mfg_cutting_checks_order_lookup');
            });
        }
        if (Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_check_one_per_order')) {
            Schema::table('mfg_cutting_checks', function (Blueprint $table) {
                $table->dropUnique('mfg_cutting_check_one_per_order');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_check_one_per_order')) {
            Schema::table('mfg_cutting_checks', function (Blueprint $table) {
                $table->unique('cutting_order_id', 'mfg_cutting_check_one_per_order');
            });
        }
    }
};
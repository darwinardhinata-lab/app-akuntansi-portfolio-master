<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_cutting_checks', function (Blueprint $table) {
            $table->decimal('scrap_kg', 20, 2)->default(0)->after('fabric_wastage_kg');
            $table->decimal('scrap_unit_value', 20, 2)->default(0)->after('scrap_kg');
            $table->decimal('scrap_value_amount', 20, 2)->default(0)->after('scrap_unit_value');
            $table->string('journal_id', 50)->nullable()->after('wastage_cost_amount');
            $table->unique('cutting_order_id', 'mfg_cutting_check_one_per_order');
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_cutting_checks', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropUnique('mfg_cutting_check_one_per_order');
            $table->dropColumn(['scrap_kg', 'scrap_unit_value', 'scrap_value_amount', 'journal_id']);
        });
    }
};
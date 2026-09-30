<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_cutting_orders', function (Blueprint $table) {
            $table->foreignId('line_id')->nullable()->after('work_order_id')
                ->constrained('mfg_production_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_cutting_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_id');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Forward repair for databases that already ran the older migration.
        if (!Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_checks_order_lookup')) {
            Schema::table('mfg_cutting_checks', fn (Blueprint $table) => $table->index('cutting_order_id', 'mfg_cutting_checks_order_lookup'));
        }
    }

    public function down(): void
    {
        // Retain the FK supporting index: it may predate this migration and is safe to keep.
    }
};
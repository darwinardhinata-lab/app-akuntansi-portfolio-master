<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            // Model declared this relation but the schema never supplied it. No inferred backfill.
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('purchase_bills')->whereNotNull('purchase_order_id')->exists()) throw new RuntimeException('Purchase bill order evidence must be retained.');
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });
    }
};
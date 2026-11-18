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
            if (!Schema::hasColumn('purchase_bills', 'outstanding_amount')) {
                $table->decimal('outstanding_amount', 20, 2)->default(0.00)->after('grand_total');
            }
            if (!Schema::hasColumn('purchase_bills', 'allocated_amount')) {
                $table->decimal('allocated_amount', 20, 2)->default(0.00)->after('outstanding_amount');
            }
            if (!Schema::hasColumn('purchase_bills', 'payment_allocation_count')) {
                $table->unsignedInteger('payment_allocation_count')->default(0)->after('allocated_amount');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_bills')) {
            if ((Schema::hasColumn('purchase_bills', 'outstanding_amount') && DB::table('purchase_bills')->where('outstanding_amount', '>', 0)->exists())
                || (Schema::hasColumn('purchase_bills', 'allocated_amount') && DB::table('purchase_bills')->where('allocated_amount', '>', 0)->exists())
                || (Schema::hasColumn('purchase_bills', 'payment_allocation_count') && DB::table('purchase_bills')->where('payment_allocation_count', '>', 0)->exists())) {
                throw new \RuntimeException('Cannot rollback: purchase bills have outstanding allocations.');
            }
            Schema::table('purchase_bills', function (Blueprint $table) {
                $cols = array_filter(['payment_allocation_count', 'allocated_amount', 'outstanding_amount'], fn($c) => Schema::hasColumn('purchase_bills', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

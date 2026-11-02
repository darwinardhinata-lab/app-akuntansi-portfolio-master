<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->decimal('outstanding_amount', 20, 2)->default(0.00)->after('grand_total');
            $table->decimal('allocated_amount', 20, 2)->default(0.00)->after('outstanding_amount');
            $table->unsignedInteger('payment_allocation_count')->default(0)->after('allocated_amount');
        });
    }

    public function down(): void
    {
        if (DB::table('purchase_bills')->where('outstanding_amount', '>', 0)->exists()
            || DB::table('purchase_bills')->where('allocated_amount', '>', 0)->exists()
            || DB::table('purchase_bills')->where('payment_allocation_count', '>', 0)->exists()) {
            throw new \RuntimeException('Cannot rollback: purchase bills have outstanding allocations.');
        }
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropColumn(['payment_allocation_count', 'allocated_amount', 'outstanding_amount']);
        });
    }
};
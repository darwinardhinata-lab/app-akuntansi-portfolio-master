<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->decimal('outstanding_amount', 20, 2)->default('0.00')->after('matched_by');
            $table->decimal('allocated_amount', 20, 2)->default('0.00')->after('outstanding_amount');
            $table->unsignedInteger('payment_allocation_count')->default(0)->after('allocated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropColumn(['outstanding_amount', 'allocated_amount', 'payment_allocation_count']);
        });
    }
};
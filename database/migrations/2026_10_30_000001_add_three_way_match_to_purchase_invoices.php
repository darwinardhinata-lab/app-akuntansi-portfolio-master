<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->string('match_status')->nullable()->after('payment_status');
            $table->decimal('match_tolerance_qty', 5, 2)->nullable()->after('match_status');
            $table->decimal('match_tolerance_price', 5, 2)->nullable()->after('match_tolerance_qty');
            $table->timestamp('matched_at')->nullable()->after('match_tolerance_price');
            $table->foreignId('matched_by')->nullable()->constrained('users')->restrictOnDelete()->after('matched_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropForeign(['matched_by']);
            $table->dropColumn(['match_status', 'match_tolerance_qty', 'match_tolerance_price', 'matched_at', 'matched_by']);
        });
    }
};
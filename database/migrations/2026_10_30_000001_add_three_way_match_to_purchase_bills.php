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
            if (!Schema::hasColumn('purchase_bills', 'match_status')) {
                $table->string('match_status', 20)->default('pending')->after('payment_status');
            }
            if (!Schema::hasColumn('purchase_bills', 'match_tolerance_qty')) {
                $table->decimal('match_tolerance_qty', 5, 2)->default(0.00)->after('match_status');
            }
            if (!Schema::hasColumn('purchase_bills', 'match_tolerance_price')) {
                $table->decimal('match_tolerance_price', 5, 2)->default(0.00)->after('match_tolerance_qty');
            }
            if (!Schema::hasColumn('purchase_bills', 'matched_at')) {
                $table->timestamp('matched_at')->nullable()->after('match_tolerance_price');
            }
            if (!Schema::hasColumn('purchase_bills', 'matched_by')) {
                $table->foreignId('matched_by')->nullable()->constrained('users')->restrictOnDelete()->after('matched_at');
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            $check = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_bills' AND CONSTRAINT_NAME = 'purchase_bills_match_status_check'");
            if (empty($check)) {
                DB::statement("ALTER TABLE purchase_bills ADD CONSTRAINT purchase_bills_match_status_check
                    CHECK (match_status IN ('pending', 'matched', 'exception', 'waived'))");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_bills') && Schema::hasColumn('purchase_bills', 'match_status')) {
            if (DB::table('purchase_bills')->whereNotNull('match_status')->where('match_status', '<>', 'pending')->exists()) {
                throw new \RuntimeException('Cannot rollback: purchase bills have non-pending match status.');
            }
            Schema::table('purchase_bills', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_bills', 'matched_by')) {
                    $table->dropConstrainedForeignId('matched_by');
                }
                $cols = array_filter(['matched_at', 'match_tolerance_price', 'match_tolerance_qty', 'match_status'], fn($c) => Schema::hasColumn('purchase_bills', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

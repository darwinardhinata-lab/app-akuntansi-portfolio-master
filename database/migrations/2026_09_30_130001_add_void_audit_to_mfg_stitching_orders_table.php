<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_stitching_orders', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('total_stitching_cost');
            $table->timestamp('voided_at')->nullable()->after('journal_id');
            $table->string('reversal_journal_id', 50)->nullable()->after('voided_at');
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreign('reversal_journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_stitching_orders', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropForeign(['reversal_journal_id']);
            $table->dropColumn(['journal_id', 'voided_at', 'reversal_journal_id']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_payment_allocations', function (Blueprint $table) {
            $table->string('reversal_journal_id', 100)->nullable()->unique();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payment_allocations', function (Blueprint $table) {
            $table->dropUnique(['reversal_journal_id']);
            $table->dropColumn(['reversal_journal_id', 'reversed_by', 'reversed_at', 'reversal_reason']);
        });
    }
};
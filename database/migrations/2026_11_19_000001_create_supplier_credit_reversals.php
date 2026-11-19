<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_credit_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binding_id')->unique()->constrained('supplier_credit_bindings')->restrictOnDelete();
            $table->foreignId('reconciliation_id')->nullable()->unique()->constrained('ppl_ap_reconciliations')->restrictOnDelete();
            $table->string('journal_id', 50)->unique();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('reversed');
            $table->date('posting_date');
            $table->string('reason', 255);
            $table->string('payload_hash', 64);
            $table->decimal('correction_amount', 20, 2);
            $table->longText('source_snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_credit_reversals')->exists()) throw new RuntimeException('Supplier credit reversal evidence must be retained.');
        Schema::drop('supplier_credit_reversals');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppl_ap_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->restrictOnDelete();
            $table->string('journal_id', 50);
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->text('reason');
            $table->string('evidence', 500);
            $table->text('source_snapshot')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('ppl_ap_reconciliation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('ppl_ap_reconciliations')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->text('reason');
            $table->timestamp('created_at');
        });
    }
    public function down(): void
    {
        if (DB::table('ppl_ap_reconciliations')->exists()) throw new RuntimeException('AP reconciliation evidence must be retained.');
        Schema::dropIfExists('ppl_ap_reconciliation_decisions');
        Schema::dropIfExists('ppl_ap_reconciliations');
    }
};
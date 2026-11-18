<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_return_ap_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reversal_id')->unique()->constrained('purchase_return_reversals')->restrictOnDelete();
            $table->foreignId('reconciliation_id')->unique()->constrained('ppl_ap_reconciliations')->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('purchase_return_ap_corrections')->exists()) {
            throw new RuntimeException('Return AP correction evidence must be retained.');
        }
        Schema::drop('purchase_return_ap_corrections');
    }
};
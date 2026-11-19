<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_credit_bindings', function (Blueprint $table) {
            $table->id();
            foreach (['company_id' => 'companies', 'party_id' => 'parties', 'purchase_bill_id' => 'purchase_bills', 'posted_by' => 'users'] as $key => $parent) {
                $table->foreignId($key)->constrained($parent)->restrictOnDelete();
            }
            $table->string('kind', 80);
            $table->string('supplier_reference', 100);
            $table->string('payload_hash', 64);
            $table->string('journal_id', 50)->unique();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreignId('ap_line_id')->unique()->constrained('journal_details')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['company_id', 'party_id', 'supplier_reference'], 'supplier_credit_source_unique');
        });
        Schema::table('ppl_ap_reconciliations', function (Blueprint $table) {
            $table->foreignId('supplier_credit_binding_id')->nullable()->unique()->constrained('supplier_credit_bindings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_credit_bindings')->exists()) {
            throw new RuntimeException('Supplier credit posting evidence must be retained.');
        }
        Schema::table('ppl_ap_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_credit_binding_id');
        });
        Schema::drop('supplier_credit_bindings');
    }
};
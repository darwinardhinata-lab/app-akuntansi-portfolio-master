<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->string('receipt_mode', 16)->nullable();
        });
        Schema::table('purchase_receipts', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_id']);
            $t->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
            $t->dropForeign(['company_id']);
            $t->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $t->uuid('idempotency_key')->nullable();
            $t->string('payload_hash', 64)->nullable();
            $t->foreignId('purchase_bill_id')->nullable()->constrained('purchase_bills')->restrictOnDelete();
            $t->unique(['company_id', 'idempotency_key'], 'grn_company_request_unique');
            $t->unique('purchase_bill_id', 'grn_bill_unique');
            $t->foreign('journal_id', 'grn_journal_restrict')->references('journal_id')->on('journal_headers')->restrictOnDelete();
        });
        Schema::table('purchase_receipt_details', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_detail_id']);
            $t->foreign('purchase_order_detail_id')->references('id')->on('purchase_order_details')->restrictOnDelete();
            $t->dropForeign(['product_id']);
            $t->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Dropping ownership/routing on documents with GRN history is not a safe rollback.
        if (DB::table('purchase_receipts')->whereNotNull('idempotency_key')->exists()
            || DB::table('purchase_orders')->where('receipt_mode', 'GRN_V1')->exists()) {
            throw new RuntimeException('GRN history exists; use a reviewed forward repair.');
        }
        Schema::table('purchase_receipt_details', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_detail_id']);
            $t->foreign('purchase_order_detail_id')->references('id')->on('purchase_order_details')->nullOnDelete();
            $t->dropForeign(['product_id']);
            $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
        Schema::table('purchase_receipts', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_id']);
            $t->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $t->dropForeign(['company_id']);
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->dropForeign('grn_journal_restrict');
            $t->dropForeign(['purchase_bill_id']);
            $t->dropUnique('grn_bill_unique');
            $t->dropUnique('grn_company_request_unique');
            $t->dropColumn(['purchase_bill_id', 'idempotency_key', 'payload_hash']);
        });
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('receipt_mode'));
    }
};

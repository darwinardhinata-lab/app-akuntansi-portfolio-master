<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_material_purchase_orders', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('DRAFT')->after('status');
            $table->string('fulfillment_status', 20)->default('OPEN')->after('approval_status');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('created_by');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['approval_status', 'fulfillment_status'], 'mfg_material_po_lifecycle_index');
        });
        Schema::table('mfg_material_purchase_order_details', function (Blueprint $table) {
            $table->unsignedBigInteger('source_request_detail_id')->nullable()->after('po_id');
            $table->foreign('source_request_detail_id', 'mfg_material_po_source_pr_detail_fk')
                ->references('id')->on('mfg_material_purchase_request_details')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_material_purchase_order_details', function (Blueprint $table) {
            $table->dropForeign('mfg_material_po_source_pr_detail_fk');
            $table->dropColumn('source_request_detail_id');
        });
        Schema::table('mfg_material_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('mfg_material_po_lifecycle_index');
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['approval_status', 'fulfillment_status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason']);
        });
    }
};
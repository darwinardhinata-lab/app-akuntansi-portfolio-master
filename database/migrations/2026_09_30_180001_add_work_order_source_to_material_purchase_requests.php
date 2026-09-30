<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_material_purchase_requests', function (Blueprint $table) {
            $table->foreignId('source_work_order_id')->nullable()->after('required_date')->constrained('mfg_work_orders')->nullOnDelete();
            $table->index(['source_work_order_id', 'approval_status'], 'mfg_material_pr_wo_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mfg_material_purchase_requests', function (Blueprint $table) {
            $table->dropIndex('mfg_material_pr_wo_status_idx');
            $table->dropConstrainedForeignId('source_work_order_id');
        });
    }
};
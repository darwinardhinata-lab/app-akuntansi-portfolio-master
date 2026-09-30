<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mfg_auxiliary_materials')) {
        Schema::create('mfg_auxiliary_materials', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 50)->unique();
            $table->string('material_name', 255);
            $table->string('hs_code', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('english_name', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->text('specification')->nullable();
            $table->decimal('meters_per_roll', 20, 2)->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->decimal('stock_quantity', 20, 2)->default(0);
            $table->decimal('average_cost', 20, 2)->default(0);
            $table->string('inventory_account_code', 20)->default('114004');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        }

        foreach (['mfg_material_ledgers', 'mfg_material_purchase_request_details', 'mfg_material_purchase_order_details', 'mfg_material_receipt_details'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('item_type', 20)->change();
            });
        }

        Schema::table('mfg_material_purchase_request_details', function (Blueprint $table) {
            if (! Schema::hasColumn('mfg_material_purchase_request_details', 'auxiliary_material_id')) {
                $table->unsignedBigInteger('auxiliary_material_id')->nullable()->after('fabric_id');
                $table->foreign('auxiliary_material_id', 'mfg_pr_aux_material_fk')->references('id')->on('mfg_auxiliary_materials')->nullOnDelete();
            }
        });
        Schema::table('mfg_material_purchase_order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('mfg_material_purchase_order_details', 'auxiliary_material_id')) {
                $table->unsignedBigInteger('auxiliary_material_id')->nullable()->after('fabric_id');
                $table->foreign('auxiliary_material_id', 'mfg_po_aux_material_fk')->references('id')->on('mfg_auxiliary_materials')->nullOnDelete();
            }
        });
        Schema::table('mfg_material_receipt_details', function (Blueprint $table) {
            if (! Schema::hasColumn('mfg_material_receipt_details', 'auxiliary_material_id')) {
                $table->unsignedBigInteger('auxiliary_material_id')->nullable()->after('fabric_id');
                $table->foreign('auxiliary_material_id', 'mfg_mrn_aux_material_fk')->references('id')->on('mfg_auxiliary_materials')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('mfg_auxiliary_material_issues')) {
        Schema::create('mfg_auxiliary_material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number', 50)->unique();
            $table->date('issue_date');
            $table->foreignId('auxiliary_material_id')->constrained('mfg_auxiliary_materials')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('mfg_work_orders')->restrictOnDelete();
            $table->foreignId('line_id')->nullable()->constrained('mfg_production_lines')->nullOnDelete();
            $table->enum('usage_type', ['WIP', 'EXPENSE']);
            $table->decimal('qty', 20, 2);
            $table->decimal('unit_cost', 20, 2);
            $table->decimal('total_cost', 20, 2);
            $table->string('journal_id', 50);
            $table->timestamp('voided_at')->nullable();
            $table->string('reversal_journal_id', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreign('reversal_journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
        });
        }
        $issueIndexes = collect(Schema::getIndexes('mfg_auxiliary_material_issues'))->pluck('name')->all();
        Schema::table('mfg_auxiliary_material_issues', function (Blueprint $table) use ($issueIndexes) {
            if (! in_array('mfg_ami_material_usage_idx', $issueIndexes, true)) {
                $table->index(['auxiliary_material_id', 'usage_type'], 'mfg_ami_material_usage_idx');
            }
            if (! in_array('mfg_ami_work_order_void_idx', $issueIndexes, true)) {
                $table->index(['work_order_id', 'voided_at'], 'mfg_ami_work_order_void_idx');
            }
        });

        $company = DB::table('companies')->where('code', 'MGI')->first();
        if ($company && DB::table('accounts')->where('account_code', '510010')->exists()) {
            DB::table('company_coa_mappings')->updateOrInsert(
                ['company_id' => $company->id, 'semantic_key' => 'auxiliary_material_expense'],
                ['account_code' => '510010', 'active' => true, 'notes' => 'Beban bahan penolong produksi', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_auxiliary_material_issues');
        Schema::table('mfg_material_receipt_details', function (Blueprint $table) { if (Schema::hasColumn('mfg_material_receipt_details', 'auxiliary_material_id')) { $table->dropForeign('mfg_mrn_aux_material_fk'); $table->dropColumn('auxiliary_material_id'); } });
        Schema::table('mfg_material_purchase_order_details', function (Blueprint $table) { if (Schema::hasColumn('mfg_material_purchase_order_details', 'auxiliary_material_id')) { $table->dropForeign('mfg_po_aux_material_fk'); $table->dropColumn('auxiliary_material_id'); } });
        Schema::table('mfg_material_purchase_request_details', function (Blueprint $table) { if (Schema::hasColumn('mfg_material_purchase_request_details', 'auxiliary_material_id')) { $table->dropForeign('mfg_pr_aux_material_fk'); $table->dropColumn('auxiliary_material_id'); } });
        Schema::dropIfExists('mfg_auxiliary_materials');
    }
};
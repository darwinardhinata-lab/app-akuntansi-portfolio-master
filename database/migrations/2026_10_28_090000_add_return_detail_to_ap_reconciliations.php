<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppl_ap_reconciliations', function (Blueprint $table) {
            $table->foreignId('purchase_return_detail_id')->nullable()->constrained('purchase_return_details')->restrictOnDelete();
            $table->decimal('quantity', 20, 6)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('ppl_ap_reconciliations')->whereNotNull('purchase_return_detail_id')->exists()) {
            throw new RuntimeException('Return allocation evidence must be retained.');
        }
        Schema::table('ppl_ap_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_return_detail_id');
            $table->dropColumn('quantity');
        });
    }
};
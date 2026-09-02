<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->string('ref_bill_number')->nullable()->after('nominal');
            $table->string('ref_po_number')->nullable()->after('ref_bill_number');
            $table->index('ref_bill_number', 'idx_pp_ref_bill');
            $table->index('ref_po_number', 'idx_pp_ref_po');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropIndex('idx_pp_ref_bill');
            $table->dropIndex('idx_pp_ref_po');
            $table->dropColumn(['ref_bill_number', 'ref_po_number']);
        });
    }
};
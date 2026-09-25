<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Nullable for staged deployment; A2 readiness rejects existing unowned rows.
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->index(['company_id', 'transaction_date'], 'po_company_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // See the SO migration: FK harus dilepas sebelum index gabungan dan kolom.
            $table->dropForeign(['company_id']);
            $table->dropIndex('po_company_date_index');
            $table->dropColumn('company_id');
        });
    }
};

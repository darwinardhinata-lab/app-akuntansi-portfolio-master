<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->index(['company_id', 'transaction_date'], 'so_company_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // MySQL/InnoDB memakai index gabungan so_company_date_index untuk FK company_id
            // (index FK otomatis dibuang karena index gabungan sudah memenuhi).
            // Karena itu constraint harus dilepas lebih dulu, baru index, lalu kolom.
            $table->dropForeign(['company_id']);
            $table->dropIndex('so_company_date_index');
            $table->dropColumn('company_id');
        });
    }
};

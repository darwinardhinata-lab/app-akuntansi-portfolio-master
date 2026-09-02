<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom journal_id (nullable string(50) + index) ke 3 tabel transaksional:
     * - sales_invoices
     * - purchase_bills
     * - transaksi_payment_plan
     *
     * Ini melengkapi backlog item "journal_id FK columns" yang masih pending,
     * mengubah linking dari string-matching (evidence_number) menjadi foreign key asli.
     */
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('grand_total');
            $table->index('journal_id');
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('grand_total');
            $table->index('journal_id');
        });

        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('status_payment');
            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex(['journal_id']);
            $table->dropColumn('journal_id');
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropIndex(['journal_id']);
            $table->dropColumn('journal_id');
        });

        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropIndex(['journal_id']);
            $table->dropColumn('journal_id');
        });
    }
};
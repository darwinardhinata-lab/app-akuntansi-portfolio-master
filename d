<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom journal_id ke tabel sales_returns dan purchase_returns.
     *
     * Migrasi ini melengkapi backlog item "journal_id FK columns" yang masih
     * pending untuk tabel retur. Dengan adanya kolom ini, sistem dapat
     * menghubungkan dokumen retur langsung ke jurnal akuntansinya secara
     * eksplisit (bukan hanya melalui evidence_number string matching).
     */
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('total_refund_amount');
            $table->index('journal_id');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('journal_id', 50)->nullable()->after('total_return_amount');
            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropIndex(['journal_id']);
            $table->dropColumn('journal_id');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropIndex(['journal_id']);
            $table->dropColumn('journal_id');
        });
    }
};

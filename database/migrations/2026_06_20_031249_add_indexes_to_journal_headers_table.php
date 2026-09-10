<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_headers', function (Blueprint $table) {
            // Add evidence_number column if it doesn't exist (missing from original schema)
            if (!Schema::hasColumn('journal_headers', 'evidence_number')) {
                $table->string('evidence_number', 150)->nullable()->after('source_doc_no');
            }
            $table->index('evidence_number');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_headers', function (Blueprint $table) {
            $table->dropIndex(['evidence_number']);
            $table->dropIndex(['transaction_date']);
            if (Schema::hasColumn('journal_headers', 'evidence_number')) {
                $table->dropColumn('evidence_number');
            }
        });
    }
};

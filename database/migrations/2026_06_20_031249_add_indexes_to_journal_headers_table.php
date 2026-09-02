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
        });
    }
};

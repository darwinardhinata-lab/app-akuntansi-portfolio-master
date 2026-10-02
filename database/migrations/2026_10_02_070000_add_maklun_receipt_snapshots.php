<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mfg_grey_fabric_receipts', 'mfg_fabric_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('coa_snapshot')->nullable();
                $table->string('journal_id')->nullable();
                $table->string('reversal_journal_id')->nullable();
                $table->string('posting_status', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['mfg_grey_fabric_receipts', 'mfg_fabric_receipts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['coa_snapshot', 'journal_id', 'reversal_journal_id', 'posting_status']));
        }
    }
};

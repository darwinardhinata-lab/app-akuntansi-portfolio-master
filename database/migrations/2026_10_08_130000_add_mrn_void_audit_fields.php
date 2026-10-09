<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mfg_material_receipts', function (Blueprint $table) {
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('reversal_journal_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_material_receipts', fn (Blueprint $table) => $table->dropColumn(['void_reason', 'voided_by', 'voided_at', 'reversal_journal_id']));
    }
};
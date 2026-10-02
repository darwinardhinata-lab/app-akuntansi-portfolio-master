<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues', 'mfg_grey_fabric_receipts', 'mfg_fabric_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('reversal_reason')->nullable();
                $table->unsignedBigInteger('reversed_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues', 'mfg_grey_fabric_receipts', 'mfg_fabric_receipts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['reversal_reason', 'reversed_by']));
        }
    }
};

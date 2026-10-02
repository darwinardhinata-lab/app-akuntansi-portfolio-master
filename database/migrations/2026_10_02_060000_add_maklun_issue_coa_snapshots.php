<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('source_account_code', 20)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('source_account_code'));
        }
    }
};

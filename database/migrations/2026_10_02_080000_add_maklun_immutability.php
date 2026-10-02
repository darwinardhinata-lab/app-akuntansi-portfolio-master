<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_headers', fn (Blueprint $table) => $table->boolean('maklun_sealed')->default(false));
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_number')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('journal_headers', fn (Blueprint $table) => $table->dropColumn('maklun_sealed'));
        foreach (['mfg_yarn_issues', 'mfg_fabric_issues'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['reversed_at', 'reversal_number']));
        }
    }
};

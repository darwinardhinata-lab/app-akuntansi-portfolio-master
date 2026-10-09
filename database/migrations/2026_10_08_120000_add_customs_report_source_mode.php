<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cbr_report_periods', function (Blueprint $table) {
            // Unknown historical provenance must never opt in to destructive rebuild.
            $table->string('source_mode', 20)->default('LEGACY');
        });
    }

    public function down(): void
    {
        Schema::table('cbr_report_periods', fn (Blueprint $table) => $table->dropColumn('source_mode'));
    }
};
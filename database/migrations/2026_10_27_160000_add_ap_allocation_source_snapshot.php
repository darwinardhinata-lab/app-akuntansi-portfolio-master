<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppl_ap_allocations', function (Blueprint $table) {
            // Existing rows deliberately remain null: never invent historical approval evidence.
            $table->text('source_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('ppl_ap_allocations')->whereNotNull('source_snapshot')->exists()) {
            throw new RuntimeException('AP source snapshots must be retained.');
        }
        Schema::table('ppl_ap_allocations', fn (Blueprint $table) => $table->dropColumn('source_snapshot'));
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_material_purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('revision_no')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mfg_material_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('revision_no');
        });
    }
};
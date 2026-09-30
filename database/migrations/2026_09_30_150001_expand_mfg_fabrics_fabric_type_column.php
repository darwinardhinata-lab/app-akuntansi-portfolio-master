<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfg_fabrics', function (Blueprint $table) {
            $table->string('fabric_type', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_fabrics', function (Blueprint $table) {
            $table->string('fabric_type', 50)->change();
        });
    }
};
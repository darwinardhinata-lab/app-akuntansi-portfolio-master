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
        Schema::table('journal_details', function (Blueprint $table) {
            // FIX: Mengubah tipe kolom amount ke decimal(20,2) secara aman
            $table->decimal('amount', 20, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->float('amount', 16, 4)->change();
        });
    }
};

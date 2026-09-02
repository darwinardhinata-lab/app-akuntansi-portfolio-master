<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cek dulu apakah kolom tags sudah terlanjur dibuat atau belum
        if (!Schema::hasColumn('sales_orders', 'tags')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                // Menghilangkan 'after' agar diletakkan di urutan paling akhir dengan aman
                $table->string('tags', 255)->nullable();
            });
        }

        if (!Schema::hasColumn('purchase_orders', 'tags')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('tags', 255)->nullable();
            });
        }

        if (!Schema::hasColumn('journal_headers', 'tags')) {
            Schema::table('journal_headers', function (Blueprint $table) {
                $table->string('tags', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_orders', 'tags')) {
            Schema::table('sales_orders', function (Blueprint $table) { $table->dropColumn('tags'); });
        }
        
        if (Schema::hasColumn('purchase_orders', 'tags')) {
            Schema::table('purchase_orders', function (Blueprint $table) { $table->dropColumn('tags'); });
        }
        
        if (Schema::hasColumn('journal_headers', 'tags')) {
            Schema::table('journal_headers', function (Blueprint $table) { $table->dropColumn('tags'); });
        }
    }
};
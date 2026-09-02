<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FIX: Kolom invoice_id & invoice_no sudah ada di production DB (Jubelio sync)
        // tapi belum terdaftar di migration history Laravel. Menambahkan secara idempotent
        // agar `migrate:fresh` di environment baru menghasilkan skema yang identik.
        if (!Schema::hasColumn('sales_orders', 'invoice_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('invoice_id', 100)->nullable()->after('so_number');
            });
        }

        if (!Schema::hasColumn('sales_orders', 'invoice_no')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('invoice_no', 110)->nullable()->after('invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'invoice_no')) {
                $table->dropColumn('invoice_no');
            }
            if (Schema::hasColumn('sales_orders', 'invoice_id')) {
                $table->dropColumn('invoice_id');
            }
        });
    }
};
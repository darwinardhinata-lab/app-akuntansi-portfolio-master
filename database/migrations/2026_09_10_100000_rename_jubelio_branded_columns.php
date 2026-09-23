<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi Jubelio sudah tidak dipakai lagi. Migration ini merename kolom
 * yang sebelumnya memakai penamaan "jubelio_*" menjadi nama generik,
 * TANPA menghapus data yang sudah ada.
 *
 *  - payment_categories.jubelio_flow      -> cash_bank_flow
 *  - sales_orders.is_jubelio_shipment     -> is_marketplace_shipment
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('payment_categories', 'jubelio_flow') && !Schema::hasColumn('payment_categories', 'cash_bank_flow')) {
            Schema::table('payment_categories', function (Blueprint $table) {
                $table->renameColumn('jubelio_flow', 'cash_bank_flow');
            });
        }

        if (Schema::hasColumn('sales_orders', 'is_jubelio_shipment') && !Schema::hasColumn('sales_orders', 'is_marketplace_shipment')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->renameColumn('is_jubelio_shipment', 'is_marketplace_shipment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_categories', 'cash_bank_flow') && !Schema::hasColumn('payment_categories', 'jubelio_flow')) {
            Schema::table('payment_categories', function (Blueprint $table) {
                $table->renameColumn('cash_bank_flow', 'jubelio_flow');
            });
        }

        if (Schema::hasColumn('sales_orders', 'is_marketplace_shipment') && !Schema::hasColumn('sales_orders', 'is_jubelio_shipment')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->renameColumn('is_marketplace_shipment', 'is_jubelio_shipment');
            });
        }
    }
};

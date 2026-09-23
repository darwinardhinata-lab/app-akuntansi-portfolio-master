<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rename Jubelio-branded columns to generic names.
     */
    public function up(): void
    {
        // Rename column: sales_orders.is_jubelio_shipment → is_marketplace_shipment
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->renameColumn('is_jubelio_shipment', 'is_marketplace_shipment');
        });

        // Rename column: payment_categories.jubelio_flow → cash_bank_flow
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->renameColumn('jubelio_flow', 'cash_bank_flow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: payment_categories.cash_bank_flow → jubelio_flow
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->renameColumn('cash_bank_flow', 'jubelio_flow');
        });

        // Reverse: sales_orders.is_marketplace_shipment → is_jubelio_shipment
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->renameColumn('is_marketplace_shipment', 'is_jubelio_shipment');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->boolean('shipment_without_stock')->default(false);
        });
        Schema::table('inventory_ledgers', function (Blueprint $table) {
            $table->foreignId('shipment_invoice_detail_id')->nullable()->unique();
            $table->foreign('shipment_invoice_detail_id', 'inventory_shipment_detail_fk')
                ->references('id')->on('sales_invoice_details')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ledgers', function (Blueprint $table) {
            $table->dropForeign('inventory_shipment_detail_fk');
        });
        Schema::table('inventory_ledgers', function (Blueprint $table) {
            $table->dropUnique(['shipment_invoice_detail_id']);
            $table->dropColumn('shipment_invoice_detail_id');
        });
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->dropColumn('shipment_without_stock');
        });
    }
};
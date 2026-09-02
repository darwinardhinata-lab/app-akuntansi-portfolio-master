<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Informasi Utama
            $table->string('ref_number', 100)->nullable()->after('contact_name');
            $table->string('salesman', 100)->nullable()->after('ref_number');
            $table->string('source', 100)->default('MANUAL')->after('salesman');
            $table->string('store_name', 100)->nullable()->after('source');
            $table->text('remarks')->nullable()->after('location_name');
            
            // Pengaturan Harga
            $table->boolean('is_tax_included')->default(false)->after('remarks');

            // Penerima
            $table->string('receiver_name', 150)->nullable()->after('is_tax_included');
            $table->text('receiver_address')->nullable()->after('receiver_name');
            $table->string('receiver_phone', 50)->nullable()->after('receiver_address');

            // Pengiriman
            $table->boolean('is_cod')->default(false)->after('receiver_phone');
            $table->string('tracking_number', 100)->nullable()->after('is_cod');
            $table->decimal('total_weight', 10, 2)->default(0)->comment('Dalam Gram')->after('tracking_number');
            $table->boolean('is_jubelio_shipment')->default(false)->after('total_weight');
            $table->string('courier', 100)->nullable()->after('is_jubelio_shipment');

            // Komponen Biaya Tambahan
            $table->decimal('other_discount', 20, 2)->default(0)->after('disc_amount');
            $table->decimal('shipping_cost', 20, 2)->default(0)->after('tax_amount');
            $table->decimal('shipping_discount', 20, 2)->default(0)->after('shipping_cost');
            $table->decimal('other_cost', 20, 2)->default(0)->after('shipping_discount');
            $table->decimal('return_remaining', 20, 2)->default(0)->after('other_cost');

            // Status Lunas
            $table->boolean('is_paid')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'ref_number', 'salesman', 'source', 'store_name', 'remarks', 'is_tax_included',
                'receiver_name', 'receiver_address', 'receiver_phone', 'is_cod', 'tracking_number',
                'total_weight', 'is_jubelio_shipment', 'courier', 'other_discount', 'shipping_cost',
                'shipping_discount', 'other_cost', 'return_remaining', 'is_paid'
            ]);
        });
    }
};
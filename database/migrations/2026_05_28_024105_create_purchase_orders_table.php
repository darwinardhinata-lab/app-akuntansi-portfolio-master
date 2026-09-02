<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 100)->unique(); // Purchase Order No.
            $table->date('transaction_date');
            
            $table->string('contact_name'); // Nama Supplier
            $table->string('location_name')->nullable(); // Lokasi (Pusat dll)
            
            // Status: DRAFT, APPROVED, PARTIAL_RECEIVED, FULLY_RECEIVED, CANCELED
            $table->string('status', 30)->default('APPROVED'); 
            
            $table->decimal('sub_total', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_invoice_id')->index();
            $table->string('journal_id', 100)->unique();
            $table->decimal('amount', 20, 2);
            $table->unsignedBigInteger('allocated_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_allocations');
    }
};
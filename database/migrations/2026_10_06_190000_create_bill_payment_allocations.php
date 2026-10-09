<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bill_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_bill_id')->index();
            $table->unsignedBigInteger('id_payment')->unique();
            $table->string('journal_id', 100);
            $table->decimal('amount', 20, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payment_allocations');
    }
};
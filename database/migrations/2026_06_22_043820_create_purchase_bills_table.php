<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('purchase_bill_details');
        Schema::dropIfExists('purchase_bills');

        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 100)->unique();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('vendor_name');
            $table->string('payment_status', 30)->default('UNPAID');
            $table->decimal('sub_total', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->string('credit_account', 50)->default('22000');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_bill_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->cascadeOnDelete();
            $table->string('account_code', 50);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_bill_details');
        Schema::dropIfExists('purchase_bills');
    }
};
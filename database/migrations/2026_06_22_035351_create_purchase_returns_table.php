<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 100)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->date('return_date');
            $table->string('status', 30)->default('COMPLETED');
            $table->decimal('total_return_amount', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_code', 100);
            $table->string('description', 255)->nullable();
            $table->integer('qty_returned')->default(0);
            $table->decimal('price', 20, 2)->default(0);
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_return_details');
        Schema::dropIfExists('purchase_returns');
    }
};
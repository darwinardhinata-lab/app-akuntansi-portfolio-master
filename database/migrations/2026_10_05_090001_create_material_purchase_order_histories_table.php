<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_purchase_order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('mfg_material_purchase_orders')->restrictOnDelete();
            $table->enum('action', ['CREATED', 'SUBMITTED', 'APPROVED', 'REJECTED', 'REVISED', 'EDITED']);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedInteger('revision_no');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_purchase_order_histories');
    }
};
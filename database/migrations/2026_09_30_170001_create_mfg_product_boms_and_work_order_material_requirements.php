<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_product_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('yarn_id')->nullable()->constrained('mfg_yarns')->restrictOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained('mfg_fabrics')->restrictOnDelete();
            $table->foreignId('auxiliary_material_id')->nullable()->constrained('mfg_auxiliary_materials')->restrictOnDelete();
            $table->decimal('qty_per_unit', 20, 6);
            $table->decimal('waste_percent', 8, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'is_active'], 'mfg_bom_product_active_idx');
        });

        Schema::create('mfg_work_order_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mfg_work_orders')->cascadeOnDelete();
            $table->foreignId('bom_id')->nullable()->constrained('mfg_product_boms')->nullOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('yarn_id')->nullable();
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->unsignedBigInteger('auxiliary_material_id')->nullable();
            $table->string('item_code', 50);
            $table->string('item_name', 255);
            $table->string('unit', 20);
            $table->decimal('qty_per_unit', 20, 6);
            $table->decimal('waste_percent', 8, 4)->default(0);
            $table->decimal('qty_required', 20, 6);
            $table->decimal('unit_cost_snapshot', 20, 2)->default(0);
            $table->decimal('estimated_total_cost', 20, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index('work_order_id', 'mfg_wo_material_requirement_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_work_order_material_requirements');
        Schema::dropIfExists('mfg_product_boms');
    }
};
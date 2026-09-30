<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_purchase_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('mfg_material_purchase_requests')->cascadeOnDelete();
            $table->enum('item_type', ['YARN', 'FABRIC']);
            $table->unsignedBigInteger('yarn_id')->nullable();
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->string('item_name', 255);
            $table->decimal('qty_requested', 20, 2);
            $table->decimal('qty_ordered', 20, 2)->default(0);
            $table->string('unit', 20)->default('KGS');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('yarn_id')->references('id')->on('mfg_yarns')->nullOnDelete();
            $table->foreign('fabric_id')->references('id')->on('mfg_fabrics')->nullOnDelete();
            $table->index(['request_id', 'item_type'], 'mfg_material_pr_detail_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_purchase_request_details');
    }
};
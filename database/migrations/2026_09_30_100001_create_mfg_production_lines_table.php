<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_production_lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_code', 50)->unique();
            $table->string('line_name', 150);
            $table->string('area', 100)->nullable();
            $table->unsignedInteger('daily_capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::table('mfg_work_orders', function (Blueprint $table) {
            $table->foreignId('line_id')->nullable()->after('product_id')
                ->constrained('mfg_production_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mfg_work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_id');
        });

        Schema::dropIfExists('mfg_production_lines');
    }
};
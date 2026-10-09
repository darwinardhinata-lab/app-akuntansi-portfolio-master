<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->string('month', 7)->primary();
            $table->boolean('closed')->default(false);
            $table->timestamps();
        });
        Schema::create('accounting_period_events', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->index();
            $table->string('action', 10);
            $table->unsignedBigInteger('user_id');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_events');
        Schema::dropIfExists('accounting_periods');
    }
};
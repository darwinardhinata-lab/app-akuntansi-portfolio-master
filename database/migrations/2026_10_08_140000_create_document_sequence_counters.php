<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_sequence_counters', function (Blueprint $table) {
            $table->string('sequence_key', 64)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('document_sequence_counters');
    }
};
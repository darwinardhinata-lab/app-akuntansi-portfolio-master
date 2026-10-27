<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppl_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('doc', 100);
            $table->string('status', 40);
            $table->unsignedInteger('version')->default(1);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['company_id', 'doc']);
        });
        Schema::create('ppl_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->uuid('request_id');
            $table->foreign('request_id')->references('id')->on('ppl_requests')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('mutation_id', 100);
            $table->string('payload_hash', 64);
            $table->string('event', 50);
            $table->unsignedInteger('version');
            $table->json('result');
            $table->timestamp('created_at');
            $table->unique(['company_id', 'mutation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('ppl_requests')->exists() || DB::table('ppl_events')->exists()) throw new RuntimeException('Workflow evidence must be retained.');
        Schema::dropIfExists('ppl_events');
        Schema::dropIfExists('ppl_requests');
    }
};
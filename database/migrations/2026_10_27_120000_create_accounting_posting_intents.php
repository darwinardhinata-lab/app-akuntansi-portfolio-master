<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_posting_intents', function (Blueprint $table) {
            $table->string('idempotency_key', 150)->primary();
            $table->string('payload_hash', 64);
            $table->string('source_type', 80);
            $table->string('source_id', 150);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('journal_id', 50)->nullable()->unique();
            $table->string('reversal_of', 50)->nullable()->unique();
            $table->timestamp('sealed_at')->nullable();
            $table->timestamps();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreign('reversal_of')->references('journal_id')->on('journal_headers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('accounting_posting_intents')->exists()) {
            throw new RuntimeException('Rollback rejected: accounting posting evidence must be retained.');
        }
        Schema::dropIfExists('accounting_posting_intents');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_return_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('original_journal_id', 50)->unique();
            $table->string('journal_id', 50)->unique();
            $table->foreign('original_journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->string('evidence_number', 80)->unique();
            $table->date('posting_date');
            $table->string('reason', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('purchase_return_reversals')->exists()) {
            throw new \RuntimeException('Purchase return reversal evidence must be retained.');
        }
        Schema::drop('purchase_return_reversals');
    }
};
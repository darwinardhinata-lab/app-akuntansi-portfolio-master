<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-company financial fence serializes all bank decisions and shared residuals.
        Schema::create('ppl_financial_contexts', function (Blueprint $table) {
            $table->foreignId('company_id')->primary()->constrained('companies')->restrictOnDelete();
            $table->json('settings');
            $table->timestamps();
        });
        Schema::create('ppl_reservations', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->foreign('request_id')->references('id')->on('ppl_requests')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('account_key', 20);
            $table->string('currency', 3);
            $table->decimal('amount', 20, 2);
            $table->string('status', 20);
            $table->timestamps();
        });
        Schema::create('ppl_payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->unique();
            $table->foreign('request_id')->references('id')->on('ppl_requests')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->string('journal_id', 50)->nullable()->unique();
            $table->foreign('journal_id')->references('journal_id')->on('journal_headers')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('ppl_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->foreign('request_id')->references('id')->on('ppl_requests')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('name');
            $table->string('mime', 100);
            $table->string('sha256', 64);
            $table->string('storage_key');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['request_id', 'kind', 'sha256']);
        });
    }

    public function down(): void
    {
        foreach (['ppl_attachments', 'ppl_payment_events', 'ppl_reservations', 'ppl_financial_contexts'] as $table) {
            if (DB::table($table)->exists()) throw new RuntimeException('Bank workflow evidence must be retained.');
        }
        foreach (['ppl_attachments', 'ppl_payment_events', 'ppl_reservations', 'ppl_financial_contexts'] as $table) Schema::dropIfExists($table);
    }
};
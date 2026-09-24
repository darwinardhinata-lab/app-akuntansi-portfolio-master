<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('request_number', 64);
            $table->date('request_date');
            $table->string('category', 100);
            $table->string('account_code', 50)->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED', 'REJECTED', 'VOID'])->default('DRAFT');
            $table->string('related_document_type', 100)->nullable();
            $table->string('related_document_id', 100)->nullable();
            $table->string('journal_id', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'request_number']);
            $table->index(['related_document_type', 'related_document_id']);
            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
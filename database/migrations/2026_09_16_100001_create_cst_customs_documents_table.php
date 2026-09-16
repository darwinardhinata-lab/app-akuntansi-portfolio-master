<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cst_customs_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->comment('PIB atau PEB');
            $table->string('internal_number', 50)->unique();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('nomor_aju', 100)->nullable();
            $table->string('nomor_pendaftaran', 100)->nullable();
            $table->string('status')->default('DRAFT');
            $table->string('environment')->default('sandbox');
            $table->string('kode_kantor', 20)->nullable();
            $table->string('currency', 10)->default('IDR');
            $table->decimal('exchange_rate', 20, 4)->nullable();
            $table->decimal('total_value', 20, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('last_error_code', 50)->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('payload_hash', 64)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'document_type'], 'uniq_customs_source_doc');
            $table->index('internal_number');
            $table->index('status');
            $table->index('nomor_aju');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cst_customs_documents');
    }
};

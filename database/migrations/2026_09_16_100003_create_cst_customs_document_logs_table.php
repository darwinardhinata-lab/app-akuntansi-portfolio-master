<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cst_customs_document_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customs_document_id');
            $table->string('direction')->comment('OUTBOUND atau INBOUND');
            $table->string('event_type');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->string('correlation_id', 100)->nullable()->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customs_document_id')
                ->references('id')
                ->on('cst_customs_documents')
                ->onDelete('cascade');
            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cst_customs_document_logs');
    }
};

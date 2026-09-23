<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cst_customs_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customs_document_id');
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('customs_document_id')
                ->references('id')
                ->on('cst_customs_documents')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cst_customs_status_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cst_customs_document_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customs_document_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->string('deskripsi_barang', 255);
            $table->decimal('qty', 20, 4);
            $table->string('satuan', 20)->nullable();
            $table->decimal('berat_bersih', 20, 4)->nullable();
            $table->decimal('nilai', 20, 2)->nullable();
            $table->decimal('tarif_bm', 10, 4)->nullable();
            $table->decimal('tarif_ppn', 10, 4)->nullable();
            $table->timestamps();

            $table->foreign('customs_document_id')
                ->references('id')
                ->on('cst_customs_documents')
                ->onDelete('cascade');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cst_customs_document_details');
    }
};

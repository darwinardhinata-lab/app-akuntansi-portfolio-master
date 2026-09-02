<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('tax_code')->unique(); // Contoh: PPN-12, PPH-23
            $table->string('tax_name'); // Contoh: PPN (Pajak Pertambahan Nilai)
            $table->decimal('rate', 5, 2); // Contoh: 12.00, 2.00
            $table->enum('tax_type', ['ADDITION', 'DEDUCTION']); // Penambah atau Pemotong
            $table->string('account_code')->nullable(); // Relasi otomatis ke Buku Besar (COA)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxes');
    }
}
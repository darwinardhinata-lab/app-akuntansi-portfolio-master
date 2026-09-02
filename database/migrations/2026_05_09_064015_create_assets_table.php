<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetsTable extends Migration
{
    public function up()
    {
    // 1. TAMBAHKAN BARIS INI: Untuk otomatis menghapus tabel sisa error sebelumnya
    Schema::dropIfExists('assets');

    // 2. Sistem akan membuat tabel dengan mulus
    Schema::create('assets', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('journal_detail_id')->unique(); 
        $table->string('asset_code')->unique();
        $table->string('asset_name');
        $table->date('purchase_date');
        $table->decimal('purchase_price', 15, 2);
        $table->integer('useful_life_months')->default(0);
        $table->timestamps();

        // 3. PASTIKAN BARIS INI TETAP ADA TANDA // (KOMENTAR) DI DEPANNYA
        // $table->foreign('journal_detail_id')->references('id')->on('journal_details')->onDelete('cascade');
    });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterDivisiTable extends Migration
{
    public function up()
    {
        Schema::create('master_divisi', function (Blueprint $table) {
            $table->id('id_divisi');
            $table->string('kode_divisi', 10)->unique(); // Misal: FIN, HR
            $table->string('nama_divisi', 50);           // Misal: FINANCE, HR
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_divisi');
    }
}
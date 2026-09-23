<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cst_customs_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('environment')->unique();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('cert_path')->nullable();
            $table->text('cert_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cst_customs_credentials');
    }
};

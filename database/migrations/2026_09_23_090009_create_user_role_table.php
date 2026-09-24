<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "role pada scope company" (Bagian 7). company_id wajib diisi -
     * satu baris = satu role milik user pada satu perusahaan tertentu.
     */
    public function up(): void
    {
        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian 7: "User N:M Company/Role". Menentukan perusahaan mana saja
     * yang boleh diakses seorang user; akses tetap diperiksa ulang di
     * backend pada setiap request (Bagian 5), bukan hanya menyaring menu.
     */
    public function up(): void
    {
        Schema::create('user_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_company');
    }
};

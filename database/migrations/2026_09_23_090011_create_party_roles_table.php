<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu party bisa punya lebih dari satu peran sekaligus (mis. pemasok
     * yang juga subcontractor), sesuai desain Marvel [M02].
     */
    public function up(): void
    {
        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->enum('role', ['CUSTOMER', 'SUPPLIER', 'SUBCONTRACTOR', 'AGENT', 'FORWARDER']);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['party_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_roles');
    }
};

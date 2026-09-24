<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian 6: "Setiap mapping menyimpan source_system, source_entity,
     * source_key, source_company, target_key, transform_version,
     * approver dan checksum. Konflik tidak memakai last-write-wins.
     * Baris ambigu dikarantina sampai diputuskan pemilik data."
     * Tabel generik lintas modul - dipakai kapan pun kode/ID lama
     * (Marvel maupun Akuntansi) perlu dipetakan ke entitas target baru.
     */
    public function up(): void
    {
        Schema::create('legacy_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 50); // mis. 'akuntansi', 'marvel'
            $table->string('source_entity', 100); // mis. 'helper_codes', 'master_divisi'
            $table->string('source_key', 150);
            $table->string('source_company', 100)->nullable();
            $table->string('target_entity', 100); // mis. 'parties', 'org_units'
            $table->string('target_key', 150);
            $table->string('transform_version', 20)->default('v1');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checksum', 64)->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'QUARANTINED'])->default('PENDING');
            $table->timestamps();

            $table->unique(
                ['source_system', 'source_entity', 'source_key', 'target_entity'],
                'legacy_map_source_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_map');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STAGE 5 — menambah status VOIDED pada mfg_material_receipts agar MRN yang
 * salah input bisa dibatalkan (jurnal + kartu stok dibalik) tanpa dihapus
 * dari histori, mengikuti semangat audit trail yang sama dgn modul lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mfg_material_receipts MODIFY COLUMN status ENUM('DRAFT','POSTED','VOIDED') NOT NULL DEFAULT 'DRAFT'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mfg_material_receipts MODIFY COLUMN status ENUM('DRAFT','POSTED') NOT NULL DEFAULT 'DRAFT'");
    }
};

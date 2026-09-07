<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STAGE 5 — menambah status VOIDED pada mfg_material_receipts agar MRN yang
 * salah input bisa dibatalkan (jurnal + kartu stok dibalik) tanpa dihapus
 * dari histori, mengikuti semangat audit trail yang sama dgn modul lain.
 *
 * Menggunakan Schema::table change() agar kompatibel dengan SQLite
 * (SQLite tidak mendukung ALTER TABLE ... MODIFY COLUMN / ENUM).
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite-compatible: change column type using doctrine/dbal or raw update
        // Since SQLite stores everything as text, we just update the default
        // and validate at application level. For MySQL, the column was already
        // created as a string type in the original migration.
        Schema::table('mfg_material_receipts', function (Blueprint $table) {
            $table->string('status')->default('DRAFT')->change();
        });

        // Set existing rows to valid values (safety check)
        DB::table('mfg_material_receipts')
            ->whereNotIn('status', ['DRAFT', 'POSTED', 'VOIDED'])
            ->update(['status' => 'DRAFT']);
    }

    public function down(): void
    {
        // Revert VOIDED rows back to DRAFT on rollback
        DB::table('mfg_material_receipts')
            ->where('status', 'VOIDED')
            ->update(['status' => 'DRAFT']);

        Schema::table('mfg_material_receipts', function (Blueprint $table) {
            $table->string('status')->default('DRAFT')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            // Menambahkan kolom helper_code tepat setelah account_code
            $table->string('helper_code', 20)->nullable()->after('account_code');

            // Membuat relasi Foreign Key ke tabel helper_codes
            $table->foreign('helper_code')
                ->references('helper_code')->on('helper_codes')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            // Menghapus relasi terlebih dahulu saat di-rollback
            $table->dropForeign(['helper_code']);
            
            // Kemudian menghapus kolomnya
            $table->dropColumn('helper_code');
        });
    }
};
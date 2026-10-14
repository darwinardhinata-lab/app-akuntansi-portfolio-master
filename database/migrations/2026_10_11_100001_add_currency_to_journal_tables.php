<?php

// FIX: multicurrency — kolom default/nullable menjaga insert massal lama tanpa backfill.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_headers', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR');
            $table->decimal('exchange_rate', 20, 8)->default(1);
        });
        Schema::table('journal_details', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->index();
            $table->decimal('amount_txn', 20, 4)->nullable();
            $table->decimal('exchange_rate', 20, 8)->default(1);
        });
    }

    public function down(): void
    {
        // FIX: multicurrency — rollback ditolak jika ada snapshot valas yang akan hilang.
        if (\App\Support\ProtectedJournalQuery::table('journal_details')->where(function ($query) {
            $query->whereNotNull('amount_txn')->orWhere('currency_code', '!=', 'IDR')->orWhere('exchange_rate', '!=', 1);
        })->exists()
            || \App\Support\ProtectedJournalQuery::table('journal_headers')->where(function ($query) {
                $query->where('currency_code', '!=', 'IDR')->orWhere('exchange_rate', '!=', 1);
            })->exists()) {
            throw new \RuntimeException('Rollback ditolak: data multicurrency masih tersedia.');
        }
        Schema::table('journal_details', function (Blueprint $table) {
            $table->dropIndex(['currency_code']);
            $table->dropColumn(['currency_code', 'amount_txn', 'exchange_rate']);
        });
        Schema::table('journal_headers', fn (Blueprint $table) => $table->dropColumn(['currency_code', 'exchange_rate']));
    }
};
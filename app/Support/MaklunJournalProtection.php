<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MaklunJournalProtection
{
    public static function check(array $ids): void
    {
        if ($ids && Schema::hasColumn('journal_headers', 'maklun_sealed')
            && DB::table('journal_headers')->whereIn('journal_id', $ids)->where('maklun_sealed', true)->exists()) {
            throw new RuntimeException('Jurnal maklun sealed tidak boleh diubah/dihapus; gunakan reversal terpisah.');
        }
    }

    public static function seal(string $id): void
    {
        DB::table('journal_headers')->where('journal_id', $id)->update(['maklun_sealed' => true]);
    }
}

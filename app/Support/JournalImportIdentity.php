<?php

namespace App\Support;

use App\Models\JournalHeader;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class JournalImportIdentity
{
    /** Match the journal number, with fallbacks for imports predating journal_no. */
    public static function matching(array $numbers): Builder
    {
        return JournalHeader::where(function ($query) use ($numbers) {
            $query->whereIn('journal_no', $numbers)
                ->orWhereIn('evidence_number', $numbers)
                ->orWhere(function ($legacy) use ($numbers) {
                    $legacy->where(function ($empty) {
                        $empty->whereNull('journal_no')->orWhere('journal_no', '');
                    })->whereIn('source_doc_no', $numbers);
                });
        });
    }

    public static function replacementIds(array $numbers): array
    {
        $headers = self::matching($numbers)->lockForUpdate()->get();
        foreach ($headers as $header) {
            if (! in_array(strtoupper((string) $header->journal_type), ['IMPORT', 'MANUAL'], true)) {
                throw new RuntimeException('Gagal import: No Jurnal '.$header->journal_no.' sudah digunakan jurnal otomatis transaksi. Data lama tidak diubah.');
            }
            SourceJournalProtection::check($header);
        }

        return $headers->modelKeys();
    }
}
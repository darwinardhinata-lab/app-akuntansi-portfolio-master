<?php

namespace App\Services;

use App\Models\JournalHeader;
use Illuminate\Support\Carbon;

class OpeningBalanceService
{
    public function query()
    {
        return JournalHeader::where(function ($query) {
            $query->where('is_opening_balance', true)
                ->orWhere('source_doc_no', 'like', 'SA-%')
                ->orWhere('evidence_number', 'like', 'SA-%');
        });
    }

    /** Shared journals currently support a single operational company only. */
    public function headerForDate(string $date): JournalHeader
    {
        $date = Carbon::parse($date)->toDateString();
        $headers = $this->query()->whereDate('transaction_date', $date)->lockForUpdate()->get();
        if ($headers->count() > 1) {
            throw new \RuntimeException(__('erp.audit_opening_duplicates'));
        }

        $header = $headers->first();
        if (! $header) {
            // The primary key prevents concurrent first submissions from duplicating a period.
            $header = new JournalHeader(['journal_id' => 'JRN-SA-'.$date]);
        }
        $header->fill([
            'transaction_date' => $date,
            'source_doc_no' => 'SA-SYSTEM-'.$date,
            'is_opening_balance' => true,
            'journal_type' => 'OPENING',
            'transaction_type' => 'OPENING BALANCE',
            'notes' => 'SETUP SALDO AWAL SISTEM (OPENING BALANCE)',
        ])->save();

        return $header;
    }
}
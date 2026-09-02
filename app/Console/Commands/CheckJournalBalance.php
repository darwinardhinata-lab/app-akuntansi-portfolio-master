<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckJournalBalance extends Command
{
    protected $signature = 'jurnal:check-balance {start_date} {end_date}';
    protected $description = 'Audit jurnal yang Debet != Kredit pada rentang tanggal tertentu';

    public function handle()
    {
        $start = $this->argument('start_date');
        $end   = $this->argument('end_date');

        // 1. CEK JURNAL YANG DEBET != KREDIT PER EVIDENCE NUMBER
        $unbalanced = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->whereBetween('journal_headers.transaction_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->select(
                'journal_headers.journal_id',
                'journal_headers.evidence_number',
                'journal_headers.transaction_date',
                'journal_headers.description',
                DB::raw("SUM(CASE WHEN journal_details.position = 'DEBET' THEN journal_details.amount ELSE 0 END) as total_debet"),
                DB::raw("SUM(CASE WHEN journal_details.position = 'KREDIT' THEN journal_details.amount ELSE 0 END) as total_kredit")
            )
            ->groupBy('journal_headers.journal_id', 'journal_headers.evidence_number', 'journal_headers.transaction_date', 'journal_headers.description')
            ->havingRaw("ROUND(SUM(CASE WHEN journal_details.position = 'DEBET' THEN journal_details.amount ELSE 0 END), 2) <> ROUND(SUM(CASE WHEN journal_details.position = 'KREDIT' THEN journal_details.amount ELSE 0 END), 2)")
            ->orderBy('journal_headers.transaction_date')
            ->get();

        if ($unbalanced->isEmpty()) {
            $this->info("✅ Tidak ada evidence_number yang Debet != Kredit pada {$start} s/d {$end}.");
        } else {
            $this->error("❌ Ditemukan {$unbalanced->count()} jurnal TIDAK BALANCE:");
            $totalSelisih = 0;

            $this->table(
                ['Journal ID', 'No. Bukti', 'Tanggal', 'Deskripsi', 'Debet', 'Kredit', 'Selisih'],
                $unbalanced->map(function ($row) use (&$totalSelisih) {
                    $selisih = $row->total_debet - $row->total_kredit;
                    $totalSelisih += $selisih;
                    return [
                        $row->journal_id,
                        $row->evidence_number,
                        $row->transaction_date,
                        Str::limit($row->description, 40),
                        number_format($row->total_debet, 2, ',', '.'),
                        number_format($row->total_kredit, 2, ',', '.'),
                        number_format($selisih, 2, ',', '.'),
                    ];
                })
            );
            $this->warn("Total Selisih Kumulatif: Rp " . number_format($totalSelisih, 2, ',', '.'));
        }

        // 2. CEK "AKUN HANTU": account_code yang tidak diawali 1-9 / kosong
        // -> baris ini SILENT DROP dari Neraca & Laba Rugi karena query controller
        //    hanya menjaring prefix '1'..'9'. Jika lawan pasangannya valid, ini JUGA
        //    menyebabkan imbalance laporan walau journal_details-nya sendiri balance.
        $ghost = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->whereBetween('journal_headers.transaction_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->where(function ($q) {
                $q->whereNull('journal_details.account_code')
                  ->orWhere('journal_details.account_code', '')
                  ->orWhereRaw("LEFT(TRIM(journal_details.account_code), 1) NOT IN ('1','2','3','4','5','6','7','8','9')");
            })
            ->select('journal_headers.evidence_number', 'journal_headers.transaction_date', 'journal_details.account_code', 'journal_details.position', 'journal_details.amount')
            ->get();

        if ($ghost->isNotEmpty()) {
            $this->warn("\n⚠️  Ditemukan {$ghost->count()} baris dengan account_code TIDAK VALID (hilang dari semua laporan):");
            $this->table(['No. Bukti', 'Tanggal', 'Account Code', 'Posisi', 'Jumlah'],
                $ghost->map(fn($r) => [$r->evidence_number, $r->transaction_date, $r->account_code ?: '(KOSONG)', $r->position, number_format($r->amount, 2, ',', '.')])
            );
        }
    }
}
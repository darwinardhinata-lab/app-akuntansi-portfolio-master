<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckJournalBalance extends Command
{
    // FIX: multicurrency — audit FX opsional; jalur default tetap tanpa perubahan.
    protected $signature = 'jurnal:check-balance {start_date} {end_date} {--fx : Audit snapshot dan keseimbangan valas}';
    protected $description = 'Audit jurnal yang Debet != Kredit pada rentang tanggal tertentu';

    public function handle()
    {
        $start = $this->argument('start_date');
        $end   = $this->argument('end_date');

        // FIX: multicurrency — jalur FX portable SQLite/MySQL, tanpa query LEFT legacy.
        if ($this->option('fx')) {
            return $this->checkFx((string) $start, (string) $end);
        }

        // 1. CEK JURNAL YANG DEBET != KREDIT PER EVIDENCE NUMBER
        $unbalanced = \App\Support\ProtectedJournalQuery::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->whereBetween('journal_headers.transaction_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->select(
                'journal_headers.journal_id',
                'journal_headers.evidence_number',
                'journal_headers.transaction_date',
                'journal_headers.notes',
                DB::raw("SUM(CASE WHEN journal_details.position = 'DEBET' THEN journal_details.amount ELSE 0 END) as total_debet"),
                DB::raw("SUM(CASE WHEN journal_details.position = 'KREDIT' THEN journal_details.amount ELSE 0 END) as total_kredit")
            )
            ->groupBy('journal_headers.journal_id', 'journal_headers.evidence_number', 'journal_headers.transaction_date', 'journal_headers.notes')
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
        $ghost = \App\Support\ProtectedJournalQuery::table('journal_details')
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

    // FIX: multicurrency — audit hanya membaca, termasuk snapshot yang diubah lewat insert langsung.
    private function checkFx(string $start, string $end): int
    {
        $failed = false;
        foreach (\App\Support\ProtectedJournalQuery::table('journal_headers')
            ->whereDate('transaction_date', '>=', $start)->whereDate('transaction_date', '<=', $end)
            ->orderBy('journal_id')->cursor() as $header) {
            $rows = \App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $header->journal_id)->get();
            try {
                $lines = $rows->map(fn ($row) => (array) $row)->all();
                if (! \App\Support\JournalBalanceValidator::isBalanced($lines)) {
                    throw new \RuntimeException('Jurnal tidak balance dalam IDR.');
                }
                $isFx = $header->currency_code !== 'IDR' || $rows->contains(fn ($row) => $row->amount_txn !== null);
                if ($isFx) {
                    foreach ($lines as &$line) {
                        $line['amount'] = (string) $line['amount'];
                        if ($line['amount_txn'] === null) {
                            if ($line['currency_code'] !== 'IDR' || \App\Modules\Currency\Support\Money::compare((string) $line['exchange_rate'], '1') !== 0) {
                                throw new \RuntimeException('Snapshot penyesuaian FX bukan IDR.');
                            }
                            $line['fx_adjustment'] = true;
                        } else {
                            $line['amount_txn'] = (string) $line['amount_txn'];
                        }
                    }
                    unset($line);
                    \App\Modules\Currency\Services\FxPostingService::validateLines($lines, ['currency' => $header->currency_code, 'rate' => (string) $header->exchange_rate]);
                }
            } catch (\Throwable $exception) {
                $failed = true;
                $this->error('FX GAGAL '.$header->journal_id.': '.$exception->getMessage());
            }
        }
        if (! $failed) {
            $this->info('FX LULUS: snapshot dan keseimbangan jurnal valid. Audit read-only.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
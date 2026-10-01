<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckAccountingIntegrity extends Command
{
    protected $signature = 'accounting:check-integrity
                            {--require-journals : Gagal jika database tidak memiliki jurnal}';

    protected $description = 'Read-only audit integritas jurnal umum pada database yang sedang aktif';

    public function handle(): int
    {
        try {
            $headers = DB::table('journal_headers')->count();
            $details = DB::table('journal_details')->count();
            $headersWithoutDetails = DB::table('journal_headers as h')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('journal_details as d')->whereColumn('d.journal_id', 'h.journal_id'))
                ->count();
            $detailsWithoutHeaders = DB::table('journal_details as d')
                ->whereNotNull('d.journal_id')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('journal_headers as h')->whereColumn('h.journal_id', 'd.journal_id'))
                ->count();
            $unbalancedJournalIds = DB::table('journal_headers as h')
                ->join('journal_details as d', 'd.journal_id', '=', 'h.journal_id')
                ->select('h.journal_id')
                ->groupBy('h.journal_id')
                ->havingRaw("ROUND(SUM(CASE WHEN d.position = 'DEBET' THEN d.amount ELSE 0 END), 2) <> ROUND(SUM(CASE WHEN d.position = 'KREDIT' THEN d.amount ELSE 0 END), 2)");
            $unbalanced = DB::query()->fromSub($unbalancedJournalIds, 'unbalanced_journals')->count();
        } catch (Throwable $exception) {
            $this->error("Audit tidak dapat dijalankan: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->table(['Pemeriksaan', 'Hasil'], [
            ['Database aktif', (string) config('database.connections.'.config('database.default').'.database')],
            ['Journal headers', $headers],
            ['Journal details', $details],
            ['Header tanpa detail', $headersWithoutDetails],
            ['Detail tanpa header', $detailsWithoutHeaders],
            ['Jurnal tidak balance', $unbalanced],
        ]);

        $failed = $headersWithoutDetails > 0 || $detailsWithoutHeaders > 0 || $unbalanced > 0
            || ($this->option('require-journals') && ($headers === 0 || $details === 0));

        if ($failed) {
            $this->error('BLOKIR: integritas jurnal tidak memenuhi syarat pemulihan/operasional. Tidak ada perubahan database dilakukan.');

            return self::FAILURE;
        }

        $this->info('LULUS: tidak ada inkonsistensi jurnal yang terdeteksi. Audit ini read-only.');

        return self::SUCCESS;
    }
}

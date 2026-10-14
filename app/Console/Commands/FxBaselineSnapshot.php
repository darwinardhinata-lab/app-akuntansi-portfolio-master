<?php

// FIX: multicurrency — baseline aditif tanpa mengubah data atau perilaku posting.
namespace App\Console\Commands;

use App\Support\OperationalDatabaseSafety;
// FIX: multicurrency — ikuti akses jurnal terlindungi yang diwajibkan tes regresi.
use App\Support\ProtectedJournalQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FxBaselineSnapshot extends Command
{
    // FIX: multicurrency — keluaran opsional untuk perbandingan baseline antar fase.
    protected $signature = 'fx:baseline
                            {--out= : Path file JSON; relatif terhadap storage/app}
                            {--database= : Nama koneksi Laravel untuk salinan database terisolasi}';

    protected $description = 'Snapshot read-only jumlah dan nilai jurnal/dokumen sebelum multicurrency';

    // FIX: multicurrency — guard diperiksa sebelum query maupun penulisan file.
    public function handle(): int
    {
        try {
            // FIX: multicurrency — koneksi sementara mencakup seluruh query dan selalu dipulihkan.
            $name = (string) ($this->option('database') ?: DB::getDefaultConnection());
            if (! is_array(config('database.connections.'.$name))) {
                throw new RuntimeException('Nama koneksi database tidak terdaftar: '.$name);
            }
            // FIX: multicurrency — periksa konfigurasi juga bila koneksi lama masih tersimpan di manager.
            $this->guardDatabase((string) config('database.connections.'.$name.'.database'));
            $snapshot = DB::usingConnection($name, function () {
                $connection = DB::connection();
                $this->guardDatabase((string) $connection->getDatabaseName());

                return $connection->transaction(function () use ($connection) {
                    // FIX: multicurrency — periksa database aktual pada PDO penulis, bukan alias koneksi.
                    if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                        $actual = $connection->selectOne('SELECT DATABASE() AS database_name', [], false);
                        if (! $actual || ! $actual->database_name) {
                            throw new RuntimeException('Nama database aktual tidak dapat diverifikasi.');
                        }
                        $this->guardDatabase((string) $actual->database_name);
                    }

                    return $this->snapshot();
                });
            });
            $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
            $out = (string) $this->option('out');
            if ($out === '') {
                $out = 'fx_baseline_'.now()->format('Ymd_His_u').'.json';
            }
            $path = preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\)~', $out) ? $out : storage_path('app/'.$out);

            // FIX: multicurrency — mode eksklusif menjaga snapshot lama tidak tertimpa.
            $stream = @fopen($path, 'x');
            if ($stream === false) {
                throw new RuntimeException('File keluaran tidak dapat dibuat; periksa direktori, izin, atau file yang sudah ada.');
            }
            try {
                $remaining = $json;
                while ($remaining !== '') {
                    $written = fwrite($stream, $remaining);
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Penulisan snapshot gagal; file keluaran mungkin belum lengkap.');
                    }
                    $remaining = substr($remaining, $written);
                }
                if (! fflush($stream)) {
                    throw new RuntimeException('Penyimpanan snapshot gagal; file keluaran mungkin belum lengkap.');
                }
            } finally {
                fclose($stream);
            }

            $this->info('Baseline tersimpan: '.$path);
            $this->line($json);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Baseline gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    // FIX: multicurrency — baseline menolak mgi_fresh_ bahkan saat testing atau opt-in destruktif aktif.
    private function guardDatabase(string $database): void
    {
        if (str_starts_with(strtolower($database), 'mgi_fresh_')
            || OperationalDatabaseSafety::prohibitsDestructiveCommands(database: $database)) {
            throw new RuntimeException('BLOKIR: baseline menolak database operasional mgi_fresh_. Gunakan salinan terisolasi.');
        }
    }

    // FIX: multicurrency — akumulasi string/bcmath menghindari SUM SQLite berbasis float.
    private function snapshot(): array
    {
        $byAccount = [];
        $totals = [];
        $details = 0;
        foreach (ProtectedJournalQuery::table('journal_details')->select('account_code', 'position', 'amount')->orderBy('account_code')->orderBy('position')->cursor() as $row) {
            $key = json_encode([$row->account_code, $row->position], JSON_THROW_ON_ERROR);
            $position = (string) $row->position;
            $byAccount[$key] ??= ['account_code' => $row->account_code, 'position' => $row->position, 'amount' => '0.00'];
            $byAccount[$key]['amount'] = bcadd($byAccount[$key]['amount'], (string) ($row->amount ?? '0'), 2);
            $totals[$position] = bcadd($totals[$position] ?? '0.00', (string) ($row->amount ?? '0'), 2);
            $details++;
        }
        ksort($totals);

        $documents = [];
        foreach (['purchase_orders', 'sales_orders', 'sales_invoices', 'purchase_bills'] as $table) {
            $count = 0;
            $total = '0.00';
            foreach (DB::table($table)->select('grand_total')->cursor() as $row) {
                $total = bcadd($total, (string) ($row->grand_total ?? '0'), 2);
                $count++;
            }
            $documents[$table] = ['count' => $count, 'grand_total' => $total];
        }

        return [
            'journal_headers_count' => ProtectedJournalQuery::table('journal_headers')->count(),
            'journal_details_count' => $details,
            'amount_by_account_position' => array_values($byAccount),
            'amount_by_position' => $totals,
            'documents' => $documents,
        ];
    }
}
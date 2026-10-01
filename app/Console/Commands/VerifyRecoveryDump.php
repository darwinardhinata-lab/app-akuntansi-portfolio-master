<?php

namespace App\Console\Commands;

use App\Support\RecoveryDumpInspector;
use Illuminate\Console\Command;
use Throwable;

class VerifyRecoveryDump extends Command
{
    protected $signature = 'recovery:verify-dump
                            {path : Absolute path dump SQL yang akan diperiksa}
                            {--require-journals : Gagal jika journal_headers atau journal_details tidak memiliki data}';

    protected $description = 'Read-only preflight dump SQL; tidak pernah menjalankan atau mengimpor SQL';

    public function handle(): int
    {
        try {
            $report = RecoveryDumpInspector::inspect((string) $this->argument('path'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Preflight dump bersifat read-only: tidak ada SQL yang dieksekusi.');
        $this->line("File: {$report['path']}");
        $this->line("Ukuran: {$report['size']} byte");
        $this->table(
            ['Tabel', 'Struktur', 'INSERT data terdeteksi'],
            collect($report['tables'])->map(fn (array $state, string $table) => [
                $table,
                $state['structure'] ? 'YA' : 'TIDAK',
                $state['rows'] ? 'YA' : 'TIDAK',
            ])->all(),
        );

        $errors = RecoveryDumpInspector::validationErrors($report, (bool) $this->option('require-journals'));
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error("BLOKIR: {$error}");
            }

            return self::FAILURE;
        }

        $this->info('LULUS preflight. Tetap buat backup database target baru sebelum restore manual.');

        return self::SUCCESS;
    }
}

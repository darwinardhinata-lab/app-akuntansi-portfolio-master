<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDuplicateJournals extends Command
{
    protected $signature = 'journal:check-duplicates';
    protected $description = 'Deteksi jurnal ganda (double-posting) berdasarkan evidence_number + transaction_type';

    public function handle()
    {
        $this->info('🔍 Memeriksa duplikasi jurnal berdasarkan (evidence_number, transaction_type)...');

        $duplicates = DB::table('journal_headers')
            ->select('evidence_number', 'transaction_type', DB::raw('COUNT(*) as jumlah_jurnal'))
            ->whereNotNull('evidence_number')
            ->where('evidence_number', '!=', '')
            ->groupBy('evidence_number', 'transaction_type')
            ->having('jumlah_jurnal', '>', 1)
            ->orderByDesc('jumlah_jurnal')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ Tidak ditemukan duplikasi jurnal. Semua evidence_number + transaction_type unik.');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Ditemukan {$duplicates->count()} evidence_number dengan jurnal ganda:");
        $this->newLine();

        $totalDupJournals = 0;
        foreach ($duplicates as $dup) {
            $this->line("  📋 Evidence: <fg=yellow>{$dup->evidence_number}</> | Type: <fg=cyan>{$dup->transaction_type}</> | Jumlah: <fg=red>{$dup->jumlah_jurnal}</>");
            
            // Show the journal_ids involved
            $journalIds = DB::table('journal_headers')
                ->where('evidence_number', $dup->evidence_number)
                ->where('transaction_type', $dup->transaction_type)
                ->orderBy('journal_id')
                ->pluck('journal_id', 'transaction_date');
            
            foreach ($journalIds as $date => $jid) {
                $this->line("     └─ {$jid} (tanggal: {$date})");
            }
            $this->newLine();
            
            $totalDupJournals += ($dup->jumlah_jurnal - 1); // excess duplicates
        }

        $this->newLine();
        $this->warn("📊 Total jurnal berlebih (perlu dihapus): {$totalDupJournals}");
        $this->info('💡 Jalankan migrasi 2026_08_04_000001 untuk auto-cleanup duplikat, atau hapus manual.');
        
        return Command::SUCCESS;
    }
}
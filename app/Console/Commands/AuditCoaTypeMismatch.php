<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class AuditCoaTypeMismatch extends Command
{
    protected $signature = 'coa:audit-type-mismatch';
    protected $description = 'Audit read-only: cari akun dengan coa_type yang tidak cocok dengan 15 kategori resmi di dropdown Tambah/Edit Akun';

    /**
     * 15 kategori ini diambil PERSIS dari <select name="coa_type"> di
     * resources/views/account/create.blade.php dan edit.blade.php --
     * inilah taksonomi yang benar-benar dipakai form manual entry hari ini.
     */
    protected const CANONICAL_TYPES = [
        'Cash & Bank',
        'Piutang Dagang',
        'Persediaan',
        'Aset Lancar Lainnya',
        'Aset Tetap',
        'Investasi Jangka Panjang',
        'Hutang Dagang',
        'Hutang Lainnya',
        'Hutang Jangka Panjang',
        'Modal',
        'Pendapatan',
        'Pendapatan Lainnya',
        'Harga Pokok Penjualan',
        'Biaya',
        'Biaya Lainnya',
    ];

    public function handle(): int
    {
        $this->info('Mengaudit accounts.coa_type terhadap 15 kategori resmi di form Tambah/Edit Akun...');
        $this->newLine();

        $grouped = Account::query()
            ->select('coa_type')
            ->selectRaw('COUNT(*) as jumlah')
            ->groupBy('coa_type')
            ->orderByDesc('jumlah')
            ->get();

        $mismatch = $grouped->reject(fn ($row) => in_array($row->coa_type, self::CANONICAL_TYPES, true));
        // FIX: $grouped->diff($mismatch) tidak reliable untuk Eloquent Collection
        // (perbandingan objek model) -- hitung manual supaya angkanya konsisten.
        $matchCount = $grouped->count() - $mismatch->count();

        $this->line("Total nilai coa_type unik di database: {$grouped->count()}");
        $this->line("Cocok dengan kategori resmi: {$matchCount}");
        $this->error("TIDAK cocok / rawan salah tampil di dropdown Edit: {$mismatch->count()}");
        $this->newLine();

        if ($mismatch->isNotEmpty()) {
            $this->table(
                ['coa_type (nilai mentah di DB)', 'Jumlah akun terdampak'],
                $mismatch->map(fn ($row) => [$row->coa_type, $row->jumlah])->all()
            );

            $this->newLine();
            $this->warn(
                'Akun-akun di atas: kalau dibuka di form Edit Akun, dropdown "Tipe Akun" akan '
                . 'tampil KOSONG (tidak match salah satu dari 15 opsi). Kalau user save tanpa '
                . 'sadar memilih ulang kategori, coa_type asli bisa TERTIMPA jadi salah kategori.'
            );
        } else {
            $this->info('Semua akun sudah konsisten dengan 15 kategori resmi. Tidak ada temuan.');
        }

        return self::SUCCESS;
    }
}

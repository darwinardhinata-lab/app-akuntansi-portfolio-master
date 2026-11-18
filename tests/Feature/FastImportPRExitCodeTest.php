<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FastImportPRExitCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_csv_returns_nonzero_exit(): void
    {
        $missing = sys_get_temp_dir().'/nonexistent-return-'.bin2hex(random_bytes(12)).'.csv';

        $this->assertNotSame(0, Artisan::call('import:pr', ['file' => $missing]));
        $this->assertStringContainsString('File tidak ditemukan', Artisan::output());
    }

    public function test_csv_without_valid_rows_returns_nonzero_exit(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'return-pr-');
        try {
            file_put_contents($file, "tanggal,no retur,no po,sku,qty,harga,keterangan\n");

            $this->assertNotSame(0, Artisan::call('import:pr', ['file' => $file]));
            $this->assertStringContainsString('Tidak ada data valid', Artisan::output());
        } finally {
            unlink($file);
        }
    }
}
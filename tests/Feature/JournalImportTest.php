<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class JournalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_creates_header_and_details()
    {
        $this->withoutMiddleware();

        // CSV header and two rows forming balanced journal (DEBET and KREDIT)
        $header = 'Tanggal,No Jurnal,No Bukti,Deskripsi,Col5,Col6,Nilai Debet,Nilai Kredit,Akun';
        $row1 = '2026-06-01,GJ-TEST-IMP,INV-001,Desc A,,,100.00,0.00,11100 - Kas';
        $row2 = '2026-06-01,GJ-TEST-IMP,INV-001,Desc A,,,0.00,100.00,21100 - Hutang';

        $content = $header . "\n" . $row1 . "\n" . $row2 . "\n";

        $file = UploadedFile::fake()->createWithContent('jurnal_import.csv', $content);

        $response = $this->post(route('jurnal.import'), [
            'file_excel' => $file
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('journal_headers', [
            'evidence_number' => 'GJ-TEST-IMP'
        ]);

        $this->assertDatabaseHas('journal_details', [
            'account_code' => '11100',
            'position'     => 'DEBET',
            'amount'       => 100.00
        ]);

        $this->assertDatabaseHas('journal_details', [
            'account_code' => '21100',
            'position'     => 'KREDIT',
            'amount'       => 100.00
        ]);
    }
}
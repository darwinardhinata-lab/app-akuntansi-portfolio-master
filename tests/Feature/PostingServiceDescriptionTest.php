<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FIX: Regresi — 'description' yang dikirim ke PostingService::post() sebelumnya terbuang
 * diam-diam (bukan kolom & tidak ada di $fillable). Kini dipetakan ke journal_headers.notes.
 */
class PostingServiceDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_description_dipetakan_ke_notes(): void
    {
        $header = PostingService::post(
            ['transaction_date' => '2026-09-28', 'evidence_number' => 'EV-DESC-1', 'description' => 'Narasi jurnal uji', 'transaction_type' => 'Purchase Bill'],
            $this->lines()
        );

        $this->assertSame('Narasi jurnal uji', JournalHeader::find($header->getKey())->notes);
        $this->assertDatabaseCount('journal_details', 2);
    }

    public function test_notes_eksplisit_tidak_ditimpa_description(): void
    {
        $header = PostingService::post(
            ['transaction_date' => '2026-09-28', 'evidence_number' => 'EV-DESC-2', 'description' => 'Deskripsi', 'notes' => 'Catatan eksplisit', 'transaction_type' => 'Purchase Bill'],
            $this->lines()
        );

        $this->assertSame('Catatan eksplisit', JournalHeader::find($header->getKey())->notes);
    }

    public function test_tanpa_description_tetap_berfungsi(): void
    {
        $header = PostingService::post(
            ['transaction_date' => '2026-09-28', 'evidence_number' => 'EV-DESC-3', 'transaction_type' => 'Purchase Bill'],
            $this->lines()
        );

        $this->assertNull(JournalHeader::find($header->getKey())->notes);
    }

    private function lines(): array
    {
        $now = now();
        return [
            ['account_code' => '11200', 'helper_code' => null, 'position' => 'DEBET',  'amount' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['account_code' => '22000', 'helper_code' => null, 'position' => 'KREDIT', 'amount' => 100, 'created_at' => $now, 'updated_at' => $now],
        ];
    }
}

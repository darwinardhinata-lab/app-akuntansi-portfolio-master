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

    public function test_journal_search_uses_notes_column_not_description_accessor(): void
    {
        $header = PostingService::post(
            ['transaction_date' => '2026-09-28', 'evidence_number' => 'EV-SEARCH-1', 'description' => 'Referensi PC-27092601', 'transaction_type' => 'Purchase Bill'],
            $this->lines()
        );

        $journals = JournalHeader::query()
            ->where(function ($query) {
                $query->where('evidence_number', 'like', '%PC-27092601%')
                    ->orWhere('notes', 'like', '%PC-27092601%');
            })
            ->get();

        $this->assertTrue($journals->contains($header->getKey()));
    }

    public function test_direct_model_creation_preserves_legacy_description(): void
    {
        $header = JournalHeader::create([
            'transaction_date' => '2026-10-07', 'description' => 'Direct invoice narration',
            'transaction_type' => 'Sales Invoice',
        ]);
        $this->assertSame('Direct invoice narration', $header->fresh()->notes);
        $this->assertSame('Direct invoice narration', $header->fresh()->description);
        $this->assertArrayNotHasKey('description', $header->getAttributes());
    }

    public function test_direct_fill_prioritizes_explicit_notes_in_either_order(): void
    {
        foreach ([
            ['description' => 'Legacy', 'notes' => 'Explicit'],
            ['notes' => 'Explicit', 'description' => 'Legacy'],
            ['description' => 'Legacy', 'notes' => null],
        ] as $attributes) {
            $header = JournalHeader::create(array_merge(['transaction_date' => '2026-10-07'], $attributes));
            $this->assertSame($attributes['notes'], $header->fresh()->notes);
        }
    }

    public function test_direct_update_and_assignment_write_only_notes(): void
    {
        $header = JournalHeader::create(['transaction_date' => '2026-10-07', 'notes' => 'Original']);
        $header->update(['description' => 'Updated']);
        $this->assertSame('Updated', $header->fresh()->notes);
        $header->description = 'Assigned';
        $header->save();
        $this->assertSame('Assigned', $header->fresh()->notes);
        $header->update(['description' => null]);
        $this->assertNull($header->fresh()->notes);
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

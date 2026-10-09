<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditJournalSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balance_key_is_stable_and_excluded_from_activity(): void
    {
        $service = app(OpeningBalanceService::class);
        $first = DB::transaction(fn () => $service->headerForDate('2026-09-30'));
        $second = DB::transaction(fn () => $service->headerForDate('2026-09-30'));

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, JournalHeader::count());
        $this->assertSame(0, JournalHeader::excludeOpeningBalance()->count());
        $this->assertSame('SA-SYSTEM-2026-09-30', $second->source_doc_no);
        $this->assertNotSame($second->source_doc_no, $second->evidence_number);
    }

    public function test_legacy_opening_balance_is_reused_without_changing_internal_evidence(): void
    {
        $legacy = JournalHeader::create([
            'transaction_date' => '2026-09-30', 'evidence_number' => 'SA-42',
        ]);
        $header = DB::transaction(fn () => app(OpeningBalanceService::class)->headerForDate('2026-09-30'));

        $this->assertSame($legacy->getKey(), $header->getKey());
        $this->assertSame($legacy->evidence_number, $header->evidence_number);
        $this->assertTrue((bool) $header->is_opening_balance);
    }

    public function test_ambiguous_legacy_duplicates_are_not_silently_removed(): void
    {
        foreach (['SA-1', 'SA-2'] as $number) {
            JournalHeader::create(['transaction_date' => '2026-09-30', 'evidence_number' => $number]);
        }
        try {
            DB::transaction(fn () => app(OpeningBalanceService::class)->headerForDate('2026-09-30'));
            $this->fail('Duplicate opening balances must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_opening_duplicates'), $e->getMessage());
        }
        $this->assertSame(2, JournalHeader::count());
    }

    public function test_source_journal_edit_and_delete_are_blocked_and_bulk_delete_is_atomic(): void
    {
        $this->withoutMiddleware();
        $source = JournalHeader::create([
            'transaction_date' => '2026-09-30', 'journal_type' => 'AUTO',
            'transaction_type' => 'SALES INVOICE', 'source_doc_no' => 'INV-CUSTOM-1',
        ]);
        $manual = JournalHeader::create(['transaction_date' => '2026-09-30', 'journal_type' => 'MANUAL']);

        $this->get(route('jurnal.edit', $source->getKey()))->assertRedirect()->assertSessionHas('error');
        $this->put(route('jurnal.update', $source->getKey()), [
            'transaction_date' => '2026-10-01', 'description' => 'Unauthorized correction',
            'details' => [
                ['account_code' => '11101', 'position' => 'DEBET', 'amount' => '100'],
                ['account_code' => '31101', 'position' => 'KREDIT', 'amount' => '100'],
            ],
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('2026-09-30', substr($source->fresh()->transaction_date, 0, 10));
        $this->delete(route('jurnal.destroy', $source->getKey()))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('jurnal.massDestroy'), ['ids' => [$manual->getKey(), $source->getKey()]])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('journal_headers', ['journal_id' => $source->getKey()]);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $manual->getKey()]);
    }
}
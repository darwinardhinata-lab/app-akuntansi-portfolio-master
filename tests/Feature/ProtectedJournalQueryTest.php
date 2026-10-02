<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Support\MaklunJournalProtection;
use App\Support\ProtectedJournalQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProtectedJournalQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sealed_raw_mutations_fail_before_any_rows_are_changed(): void
    {
        $sealed = JournalHeader::create(['journal_id' => 'RAW-SEALED', 'transaction_date' => '2026-10-02']);
        $plain = JournalHeader::create(['journal_id' => 'RAW-PLAIN', 'transaction_date' => '2026-10-02']);
        DB::table('journal_details')->insert([
            ['journal_id' => $sealed->getKey(), 'account_code' => 'TEST', 'position' => 'DEBET', 'amount' => 10],
            ['journal_id' => $plain->getKey(), 'account_code' => 'TEST', 'position' => 'DEBET', 'amount' => 20],
        ]);
        MaklunJournalProtection::seal($sealed->getKey());
        foreach (['header_delete', 'header_update', 'detail_delete', 'detail_update', 'insert', 'ignore', 'upsert', 'truncate', 'update_or_insert'] as $operation) {
            try {
                $headers = ProtectedJournalQuery::table('journal_headers');
                $details = ProtectedJournalQuery::table('journal_details');
                match ($operation) {
                    'header_delete' => $headers->delete(),
                    'header_update' => $headers->update(['notes' => 'changed']),
                    'detail_delete' => $details->delete(),
                    'detail_update' => $details->update(['amount' => 99]),
                    'insert' => $details->insert(['journal_id' => 'RAW-SEALED', 'amount' => 1]),
                    'ignore' => $details->insertOrIgnore(['journal_id' => 'RAW-SEALED', 'amount' => 1]),
                    'upsert' => $details->upsert([['journal_id' => 'RAW-SEALED']], 'journal_id'),
                    'truncate' => $headers->truncate(),
                    'update_or_insert' => $headers->updateOrInsert(['journal_id' => 'RAW-SEALED'], ['notes' => 'changed']),
                };
                $this->fail('Protected mutation accepted: '.$operation);
            } catch (\RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
            $this->assertDatabaseCount('journal_headers', 2);
            $this->assertDatabaseCount('journal_details', 2);
            $this->assertDatabaseHas('journal_details', ['journal_id' => 'RAW-PLAIN', 'amount' => 20]);
        }
        ProtectedJournalQuery::table('journal_headers')->where('journal_id', 'RAW-PLAIN')->update(['notes' => 'allowed']);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => 'RAW-PLAIN', 'notes' => 'allowed']);
    }

    public function test_detail_reparent_to_sealed_header_is_rejected(): void
    {
        JournalHeader::create(['journal_id' => 'SEALED-TARGET', 'transaction_date' => '2026-10-02']);
        MaklunJournalProtection::seal('SEALED-TARGET');
        $this->expectExceptionMessage('sealed');
        ProtectedJournalQuery::table('journal_details')->where('journal_id', 'OTHER')->update(['journal_id' => 'SEALED-TARGET']);
    }

    public function test_source_does_not_reintroduce_direct_journal_table_calls_outside_seal_support(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || in_array($file->getBasename(), ['MaklunJournalProtection.php', 'ProtectedJournalQuery.php'], true)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/DB::table\([\x27\"]journal_(?:headers|details)[\x27\"]\)/', file_get_contents($file->getPathname()), $file->getPathname());
        }
    }
}

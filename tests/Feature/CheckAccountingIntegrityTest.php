<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_passes_only_when_journals_are_not_required(): void
    {
        $this->artisan('accounting:check-integrity')
            ->expectsOutputToContain('LULUS')
            ->assertExitCode(0);

        $this->artisan('accounting:check-integrity --require-journals')
            ->expectsOutputToContain('BLOKIR')
            ->assertExitCode(1);
    }

    public function test_balanced_journal_passes_integrity_audit(): void
    {
        DB::table('journal_headers')->insert(['journal_id' => 'JU-001']);
        DB::table('journal_details')->insert([
            ['journal_id' => 'JU-001', 'position' => 'DEBET', 'amount' => 100],
            ['journal_id' => 'JU-001', 'position' => 'KREDIT', 'amount' => 100],
        ]);

        $this->artisan('accounting:check-integrity --require-journals')
            ->expectsOutputToContain('LULUS')
            ->assertExitCode(0);
    }

    public function test_orphan_or_unbalanced_journals_fail_integrity_audit(): void
    {
        DB::table('journal_headers')->insert([
            ['journal_id' => 'JU-ORPHAN'],
            ['journal_id' => 'JU-UNBALANCED'],
        ]);
        DB::table('journal_details')->insert([
            ['journal_id' => 'JU-UNBALANCED', 'position' => 'DEBET', 'amount' => 100],
            ['journal_id' => 'JU-MISSING-HEADER', 'position' => 'KREDIT', 'amount' => 50],
        ]);

        $this->artisan('accounting:check-integrity --require-journals')
            ->expectsOutputToContain('BLOKIR')
            ->assertExitCode(1);
    }
}

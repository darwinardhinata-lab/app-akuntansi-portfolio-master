<?php

namespace Tests\Feature;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingPostingIntentTest extends TestCase
{
    use RefreshDatabase;

    private function header(): array
    {
        return ['transaction_date' => '2026-10-01', 'journal_type' => 'MANUAL', 'notes' => 'Intent regression'];
    }

    private function lines(): array
    {
        return [
            ['account_code' => 'INTENT-ASSET', 'position' => 'DEBET', 'amount' => '25.50'],
            ['account_code' => 'INTENT-EQUITY', 'position' => 'KREDIT', 'amount' => '25.50'],
        ];
    }

    private function intent(): array
    {
        return ['idempotency_key' => 'manual:test:1', 'source_type' => 'MANUAL', 'source_id' => 'test:1'];
    }

    public function test_retry_returns_same_journal_and_changed_payload_is_rejected(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $first = PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
        $retry = PostingService::post(array_reverse($this->header(), true), $this->lines(), null, null, $this->intent());
        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        try {
            PostingService::post(array_merge($this->header(), ['notes' => 'changed']), $this->lines(), null, null, $this->intent());
            $this->fail('Changed payload must conflict.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflict', $exception->getMessage());
        }
        $this->assertDatabaseHas('accounting_posting_intents', ['journal_id' => $first->getKey(), 'source_type' => 'MANUAL', 'source_id' => 'test:1']);
    }

    public function test_sealed_records_reject_model_bulk_mutation_and_additional_details_even_with_flag_off(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $header = PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
        config(['platform.posting_intents_enabled' => false]);
        foreach ([
            fn () => $header->update(['notes' => 'tamper']),
            fn () => JournalHeader::whereKey($header->getKey())->delete(),
            fn () => JournalDetail::where('journal_id', $header->getKey())->update(['amount' => 1]),
            fn () => $header->details()->firstOrFail()->delete(),
            fn () => JournalDetail::insert([array_merge($this->lines()[0], ['journal_id' => $header->getKey()])]),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Sealed journal mutation accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('journal_details', 2);
    }

    public function test_failure_rolls_back_intent_and_header(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $lines = $this->lines();
        foreach ($lines as &$line) $line['invalid_column'] = true;
        unset($line);
        try {
            PostingService::post($this->header(), $lines, null, null, $this->intent());
            $this->fail('Invalid column accepted.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('invalid_column', $exception->getMessage());
        }
        $this->assertDatabaseCount('accounting_posting_intents', 0);
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_new_intent_is_disabled_by_default(): void
    {
        config(['platform.posting_intents_enabled' => false]);
        $this->expectExceptionMessage('disabled');
        PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
    }

    public function test_linked_reversal_retries_and_preserves_original_with_open_period_enforcement(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $actor = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        config(['platform.journal_reversal_user_ids' => [$actor->id]]);
        $original = PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
        DB::table('accounting_periods')->insert(['month' => '2026-10', 'closed' => true]);
        try {
            PostingService::reverse($original->getKey(), '2026-10-15', 'Correction', $actor);
            $this->fail('Closed reversal period accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('closed', $exception->getMessage());
        }
        $reversal = PostingService::reverse($original->getKey(), '2026-11-01', 'Correction', $actor);
        $retry = PostingService::reverse($original->getKey(), '2026-11-01', 'Correction', $actor);
        $this->assertSame($reversal->getKey(), $retry->getKey());
        $this->assertSame('Intent regression', $original->fresh()->notes);
        $this->assertDatabaseHas('accounting_posting_intents', ['journal_id' => $reversal->getKey(), 'reversal_of' => $original->getKey(), 'actor_id' => $actor->id]);
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('journal_details', 4);
        foreach (['INTENT-ASSET', 'INTENT-EQUITY'] as $code) {
            $query = DB::table('journal_details')->where('account_code', $code);
            $this->assertSame(0, bccomp((string) (clone $query)->where('position', 'DEBET')->sum('amount'),
                (string) (clone $query)->where('position', 'KREDIT')->sum('amount'), 2));
        }
    }

    public function test_reversal_requires_explicit_finance_allowlist(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $actor = \App\Models\User::factory()->create(['role' => 'ADMIN']);
        config(['platform.journal_reversal_user_ids' => [$actor->id]]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        PostingService::reverse('missing', '2026-11-01', 'Correction', $actor);
    }

    public function test_fx_retry_and_reversal_preserve_original_rate_and_transaction_amounts(): void
    {
        config(['platform.posting_intents_enabled' => true, 'platform.multicurrency_enabled' => true]);
        $this->seed(\Database\Seeders\CurrencySeeder::class);
        \App\Modules\Platform\Models\Company::create([
            'code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true,
        ]);
        foreach (['INTENT-ASSET' => 'DEBET', 'INTENT-EQUITY' => 'KREDIT'] as $code => $normal) {
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => $code, 'coa_type' => 'Other',
                'normal_balance' => $normal, 'report_pos' => 'NERACA', 'currency_code' => 'USD',
            ]);
        }
        $lines = $this->lines();
        foreach ($lines as &$line) {
            $line['amount_txn'] = '1.00';
            $line['amount'] = '15800.00';
        }
        unset($line);
        $fx = ['currency' => 'USD', 'rate' => '15800.00000000'];
        $original = PostingService::post($this->header(), $lines, null, $fx, $this->intent());
        $this->assertSame($original->getKey(), PostingService::post($this->header(), $lines, null, $fx, $this->intent())->getKey());
        $actor = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        config(['platform.journal_reversal_user_ids' => [$actor->id]]);
        $reversal = PostingService::reverse($original->getKey(), '2026-11-01', 'FX correction', $actor);
        $this->assertSame('USD', $reversal->currency_code);
        $this->assertSame(0, bccomp((string) $reversal->exchange_rate, '15800', 8));
        foreach ($original->details()->orderBy('id')->get() as $detail) {
            $opposite = $reversal->details()->where('account_code', $detail->account_code)->firstOrFail();
            $this->assertNotSame($detail->position, $opposite->position);
            $this->assertSame('USD', $opposite->currency_code);
            $this->assertSame(0, bccomp((string) $detail->amount_txn, (string) $opposite->amount_txn, 4));
            $this->assertSame(0, bccomp((string) $detail->amount, (string) $opposite->amount, 2));
            $this->assertSame(0, bccomp((string) $detail->exchange_rate, (string) $opposite->exchange_rate, 8));
        }
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('journal_details', 4);
    }

    public function test_used_intent_migration_cannot_drop_accounting_evidence(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
        $migration = require database_path('migrations/2026_10_27_120000_create_accounting_posting_intents.php');
        try {
            $migration->down();
            $this->fail('Used accounting evidence must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('retained', $exception->getMessage());
        }
        $this->assertDatabaseCount('accounting_posting_intents', 1);
    }

    public function test_changed_reversal_reason_conflicts_instead_of_posting_twice(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $actor = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        config(['platform.journal_reversal_user_ids' => [$actor->id]]);
        $original = PostingService::post($this->header(), $this->lines(), null, null, $this->intent());
        PostingService::reverse($original->getKey(), '2026-11-01', 'Correction', $actor);
        try {
            PostingService::reverse($original->getKey(), '2026-11-01', 'Changed reason', $actor);
            $this->fail('Conflicting reversal accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflict', $exception->getMessage());
        }
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('accounting_posting_intents', 2);
    }
}
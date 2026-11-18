<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualReversalBankCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $receipt = '500.00', string $hold = '400.00'): array
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        config(['platform.posting_intents_enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.erp_bank_balance' => true]);
        $actor = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.journal_reversal_user_ids' => [$actor->id]]);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['111003', '310001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic',
                'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
        }
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111003', 'active' => true]);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id,
            'settings' => json_encode(['erp_cutoff' => '2026-01-01', 'accounts' => [['id' => 'idr3', 'currency' => 'IDR']]])]);
        $id = 'e3c6df8f-5f40-4444-8997-b7aa92c6480e';
        DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $company->id, 'owner_user_id' => $actor->id,
            'doc' => 'SYNTHETIC-HOLD', 'status' => 'bank_maker', 'version' => 1, 'payload' => '{}']);
        DB::table('ppl_reservations')->insert(['request_id' => $id, 'company_id' => $company->id,
            'account_key' => 'idr3', 'currency' => 'IDR', 'amount' => $hold, 'status' => 'active']);
        $original = PostingService::post(['transaction_date' => '2026-01-02', 'journal_type' => 'MANUAL'], [
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => $receipt],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => $receipt],
        ], null, null, ['idempotency_key' => 'manual:bank-receipt', 'source_type' => 'MANUAL', 'source_id' => 'bank-receipt']);

        return [$actor, $original];
    }

    public function test_reversal_cannot_remove_bank_capacity_already_reserved(): void
    {
        [$actor, $original] = $this->fixture();
        $before = $this->inventory();
        try {
            PostingService::reverse($original->getKey(), '2026-01-03', 'Synthetic correction', $actor);
            $this->fail('Reversal spent reserved bank funds.');
        } catch (RuleViolation $exception) {
            $this->assertSame('funds_insufficient', $exception->rule);
        }
        $this->assertSame($before, $this->inventory());
    }

    public function test_affordable_reversal_retry_does_not_check_or_spend_capacity_twice(): void
    {
        [$actor, $original] = $this->fixture('100.00', '400.00');
        PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '400.00'],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '400.00'],
        ]);
        $reversal = PostingService::reverse($original->getKey(), '2026-01-03', 'Synthetic correction', $actor);
        $before = $this->inventory();
        $this->assertSame($reversal->getKey(), PostingService::reverse($original->getKey(), '2026-01-03', 'Synthetic correction', $actor)->getKey());
        $this->assertSame($before, $this->inventory());
        $this->assertSame(2, $reversal->details()->count());
        $this->assertSame(3, DB::table('journal_headers')->count());
    }

    public function test_enabled_workflow_takes_financial_fence_before_journal_lock(): void
    {
        [$actor, $original] = $this->fixture('100.00', '0.00');
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        PostingService::reverse($original->getKey(), '2026-01-03', 'Synthetic correction', $actor);
        $context = array_key_first(array_filter($queries, fn ($sql) => str_contains($sql, 'from "ppl_financial_contexts"')));
        $journal = array_key_first(array_filter($queries, fn ($sql) => str_contains($sql, 'from "journal_headers"')));
        $this->assertNotNull($context);
        $this->assertNotNull($journal);
        $this->assertLessThan($journal, $context);
    }

    public function test_workflow_disabled_keeps_existing_manual_reversal_contract(): void
    {
        [$actor, $original] = $this->fixture();
        config(['payment_plan_v33.bank_workflow' => false]);
        $reversal = PostingService::reverse($original->getKey(), '2026-01-03', 'Synthetic correction', $actor);
        $this->assertSame('REVERSAL', $reversal->journal_type);
        $this->assertDatabaseCount('journal_headers', 2);
    }

    private function inventory(): array
    {
        $rows = [];
        foreach (['journal_headers', 'journal_details', 'accounting_posting_intents', 'ppl_reservations', 'ppl_payment_events'] as $table) {
            $rows[$table] = DB::table($table)->get()->toJson();
        }

        return $rows;
    }
}
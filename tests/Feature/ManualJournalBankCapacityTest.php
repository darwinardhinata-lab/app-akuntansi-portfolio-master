<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ManualJournalBankCapacityTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.erp_bank_balance' => true]);
        $actor = User::factory()->create(['role' => 'FINANCE']);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['111003', '310001', '610001'] as $code) DB::table('accounts')->insert(['account_code' => $code,
            'account_name' => 'Synthetic', 'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111003', 'active' => true]);
        $settings = ['erp_cutoff' => '2026-01-01', 'accounts' => [['id' => 'idr3', 'currency' => 'IDR']]];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings)]);
        $request = (string) \Illuminate\Support\Str::uuid();
        DB::table('ppl_requests')->insert(['id' => $request, 'company_id' => $company->id, 'owner_user_id' => $actor->id,
            'doc' => 'HELD-IDR3', 'status' => 'bank_maker', 'payload' => '{}']);
        DB::table('ppl_reservations')->insert(['request_id' => $request, 'company_id' => $company->id,
            'account_key' => 'idr3', 'currency' => 'IDR', 'amount' => '400.00', 'status' => 'active']);
        PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '500.00'],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '500.00']]);
        $this->actingAs($actor);
        return compact('company', 'settings');
    }

    private function state(array $f): array
    {
        $state = [];
        foreach (['journal_headers', 'journal_details', 'accounting_posting_intents', 'ppl_reservations', 'ppl_payment_events', 'assets'] as $table) {
            $state[$table] = DB::table($table)->get()->toJson();
        }
        $state['balance'] = app(ErpBankBalance::class)->project($f['company']->id, ['id' => 'idr3', 'currency' => 'IDR'], $f['settings']);
        return $state;
    }

    public function test_manual_journal_cannot_spend_reserved_idr3_and_affordable_write_preserves_hold(): void
    {
        $f = $this->fixture();
        $before = $this->state($f);
        $this->assertSame('100.00', $before['balance']['available']);
        $payload = ['transaction_date' => '2026-01-03', 'description' => 'Manual reserved bank capacity probe', 'details' => [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'],
            ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '200.00']]];
        $response = $this->post(route('jurnal.store'), $payload);
        $after = $this->state($f);
        if ($path = getenv('MANUAL_WRITER_EVIDENCE')) file_put_contents($path, json_encode([
            'before' => $before['balance'], 'after' => $after['balance'], 'unchanged' => $before === $after,
            'http_status' => $response->status(), 'journals_before' => count(json_decode($before['journal_headers'])),
            'journals_after' => count(json_decode($after['journal_headers']))], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
        $response->assertSessionHas('error');
        $this->assertEquals($before, $after);
        foreach ($payload['details'] as &$line) $line['amount'] = '100.00';
        unset($line);
        $this->post(route('jurnal.store'), $payload)->assertSessionHas('success');
        $balance = $this->state($f)['balance'];
        $this->assertSame('400.00', $balance['actual']);
        $this->assertSame('400.00', $balance['reserved']);
        $this->assertSame('0.00', $balance['available']);
        $this->assertDatabaseCount('journal_headers', 2);
    }
}
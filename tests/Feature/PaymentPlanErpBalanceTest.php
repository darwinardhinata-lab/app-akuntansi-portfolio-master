<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanErpBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_erp_receipts_and_payment_before_after_ack_are_counted_once(): void
    {
        config(['platform.posting_intents_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        $user = User::factory()->create();
        foreach (['111003', '310001', '610001'] as $code) DB::table('accounts')->insert([
            'account_code' => $code, 'account_name' => 'Fixture', 'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA',
        ]);
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111003', 'active' => true]);
        foreach ([['2025-12-31', '1000.00'], ['2026-01-02', '50.25']] as [$date, $amount]) {
            PostingService::post(['transaction_date' => $date], [['account_code' => '111003', 'position' => 'DEBET', 'amount' => $amount], ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => $amount]]);
        }
        $id = 'c15bb104-00da-4ad9-b3de-269c44f3eb67';
        $event = '01b2fe6c-704b-4d20-b70d-0c0dddf35e35';
        DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $company->id, 'owner_user_id' => $user->id, 'doc' => 'TEST', 'status' => 'paid', 'version' => 1, 'payload' => '{}']);
        DB::table('ppl_payment_events')->insert(['id' => $event, 'request_id' => $id, 'company_id' => $company->id, 'confirmed_by' => $user->id,
            'snapshot' => json_encode(['paidDate' => '2026-01-03', 'settlement' => ['sourceId' => 'idr3', 'debit' => '100.10']]), 'created_at' => now()]);
        $service = new ErpBankBalance();
        $account = ['id' => 'idr3', 'currency' => 'IDR'];
        $settings = ['erp_cutoff' => '2026-01-01'];
        $before = $service->project($company->id, $account, $settings);
        $this->assertSame('1000.00', $before['opening']);
        $this->assertSame('50.25', $before['movement']);
        $this->assertSame('100.10', $before['unposted_actual_debits']);
        $this->assertSame('950.15', $before['actual']);
        $journal = PostingService::post(['transaction_date' => '2026-01-03'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '100.10'], ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '100.10'],
        ], null, null, ['idempotency_key' => 'ppl:paid:'.$event, 'source_type' => 'PPL_PAYMENT_EVENT', 'source_id' => $event]);
        // Deliberately leave ACK null: recover through sealed PostingService intent identity.
        $crash = $service->project($company->id, $account, $settings);
        $this->assertSame('0.00', $crash['unposted_actual_debits']);
        $this->assertSame('950.15', $crash['actual']);
        DB::table('ppl_payment_events')->where('id', $event)->update(['journal_id' => $journal->getKey()]);
        $this->assertSame('950.15', $service->project($company->id, $account, $settings)['actual']);
    }
}
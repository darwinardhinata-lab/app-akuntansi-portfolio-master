<?php

namespace Tests\Feature;

use App\Models\PaymentPlan;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyApResidualGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_evidence_blocks_reservation_without_inferring_an_allocation(): void
    {
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['211001', '610001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic',
                'coa_type' => 'Other', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        }
        $journal = PostingService::post(['source_doc_no' => 'LEGACY-GUARD'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.50'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '200.50'],
        ])->getKey();
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'LEGACY-GUARD', 'bill_date' => '2026-01-01',
            'vendor_name' => 'Synthetic', 'credit_account' => '211001', 'grand_total' => '200.50', 'journal_id' => $journal]);
        $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'LG', 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        $payment = PaymentPlan::create(['no_transaksi' => 'LEGACY-GUARD-PAY', 'id_divisi' => $division,
            'tgl_pengajuan' => '2026-01-01', 'jenis_transaksi' => 'BANK', 'penerima_pj' => 'Synthetic',
            'keterangan' => 'Fixture', 'nominal' => '50.00', 'status_payment' => 'UNPAID', 'ref_bill_number' => 'LEGACY-GUARD']);
        $service = new PurchaseBillAllocation();
        $source = fn () => DB::transaction(fn () => $service->source($bill, $company->id));
        $this->assertArrayNotHasKey('blocked', $source());

        $payment->forceFill(['status_payment' => 'POSTED'])->save();
        $this->assertSame('ap_reconciliation_required', $source()['blocked']);

        // An explicit link is evidence even if the legacy status or text reference is inconsistent.
        $payment->forceFill(['status_payment' => 'UNPAID', 'ref_bill_number' => null])->save();
        foreach ([$payment->id_payment, $payment->id_payment + 100] as $id) {
            DB::table('bill_payment_allocations')->insert(['purchase_bill_id' => $bill, 'id_payment' => $id,
                'journal_id' => $journal, 'amount' => '50.00']);
        }
        $result = $source();
        $this->assertSame('200.50', $result['residual']);
        $this->assertSame('ap_reconciliation_required', $result['blocked']);
        $this->assertSame(['legacy_payment_reconciliation_required', 'orphan_legacy_payment_allocation'], array_column($result['exceptions'], 'reason'));
        $tables = ['ppl_ap_allocations', 'ppl_reservations', 'journal_headers', 'journal_details', 'bill_payment_allocations', 'transaksi_payment_plan'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $before = $snapshot();
        try {
            DB::transaction(fn () => $service->reserve(['id' => (string) \Illuminate\Support\Str::uuid(),
                'purchaseBillId' => $bill, 'settlement' => ['gross' => '200.50']], $company->id));
            $this->fail('Legacy evidence must not be silently treated as payable.');
        } catch (RuleViolation $exception) {
            $this->assertSame('ap_reconciliation_required', $exception->rule);
        }
        $this->assertSame($before, $snapshot());
    }
}
<?php

namespace Tests\Integration;

use App\Models\JournalHeader;
use App\Models\PaymentPlan;
use App\Models\User;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PaymentMakerChecker;
use App\Support\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/ManualJournalBankCapacityMysqlTest.php';

class LegacyPostingBankCapacityMysqlTest extends ManualJournalBankCapacityMysqlTest
{
    public function test_legacy_direct_idr_post_respects_committed_v33_hold(): void
    {
        config(['payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.erp_bank_balance' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['111101' => 'Cash & Bank', '610001' => 'Other'] as $code => $type) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic',
                'coa_type' => $type, 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        }
        $bank = ['id' => 'idr3', 'currency' => 'IDR'];
        $settings = ['erp_cutoff' => '2026-01-01', 'accounts' => [$bank]];
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111101', 'active' => true]);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => '500.00'],
            ['account_code' => '610001', 'position' => 'KREDIT', 'amount' => '500.00'],
        ]);
        $maker = User::factory()->create(['role' => 'FINANCE']);
        $checker = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_post_user_ids' => [$checker->id]]);
        $id = (string) Str::uuid();
        DB::table('ppl_requests')->insert(['id' => $id, 'doc' => 'SYNTHETIC-HOLD', 'company_id' => $company->id,
            'owner_user_id' => $maker->id, 'status' => 'bank_prepared', 'version' => 1, 'payload' => '{}']);
        DB::table('ppl_reservations')->insert(['request_id' => $id, 'company_id' => $company->id, 'account_key' => 'idr3',
            'currency' => 'IDR', 'amount' => '400.00', 'status' => 'active']);
        $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'M1', 'nama_divisi' => 'Synthetic', 'status_aktif' => 1]);
        Storage::fake('public')->put('proof/synthetic.pdf', 'synthetic');
        $newPayment = function (string $doc, string $amount) use ($maker, $checker, $division): PaymentPlan {
            $this->actingAs($maker);
            $payment = PaymentPlan::create(['no_transaksi' => $doc, 'id_divisi' => $division, 'tgl_pengajuan' => '2026-01-02',
                'tgl_transaksi' => '2026-01-02', 'jenis_transaksi' => '111101', 'kategori_payment' => 'OPERASIONAL',
                'vendor_toko' => 'Synthetic', 'penerima_pj' => 'Synthetic', 'keterangan' => 'Synthetic direct service',
                'id_akun' => '610001', 'nominal' => $amount, 'nominal_aktual' => $amount, 'status_payment' => 'PENGAJUAN']);
            $payment->details()->create(['keterangan' => 'Synthetic', 'qty' => 1, 'nominal' => $amount,
                'nominal_aktual' => $amount, 'bukti_file' => 'proof/synthetic.pdf']);
            $this->actingAs($checker);
            PaymentMakerChecker::approve($payment->refresh());
            $payment->update(['status_payment' => 'PAID']);
            return $payment->refresh();
        };
        $blocked = $newPayment('SYNTHETIC-OVER', '100.01');
        $exact = $newPayment('SYNTHETIC-EXACT', '100.00');
        $balance = fn () => app(ErpBankBalance::class)->project($company->id, $bank, $settings);
        $reservation = DB::table('ppl_reservations')->where('request_id', $id)->first();
        $this->assertSame('100.00', $balance()['available']);
        $before = $blocked->getAttributes();
        $response = $this->post(route('payment.post_journal'), ['selected_ids' => $blocked->no_transaksi]);
        $this->assertSame('PAID', $blocked->fresh()->status_payment, (string) session('error'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('funds_insufficient', (string) session('error'));
        $this->assertSame($before, $blocked->fresh()->getAttributes());
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertSame('100.00', $balance()['available']);
        $this->post(route('payment.post_journal'), ['selected_ids' => $exact->no_transaksi])->assertSessionHas('success');
        $this->assertSame('POSTED', $exact->fresh()->status_payment);
        $this->assertDatabaseHas('journal_details', ['journal_id' => JournalHeader::idForPaymentPlan($exact->no_transaksi),
            'account_code' => '111101', 'position' => 'KREDIT', 'amount' => '100.00']);
        $this->assertSame('0.00', $balance()['available']);
        $this->assertEquals($reservation, DB::table('ppl_reservations')->where('request_id', $id)->first());
        $this->assertDatabaseCount('ppl_payment_events', 0);
    }
}
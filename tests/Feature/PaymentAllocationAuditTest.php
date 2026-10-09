<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthorizePaymentAction;
use App\Models\PaymentPlan;
use App\Models\PurchaseBill;
use App\Models\User;
use App\Services\BillPaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentAllocationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function payment(float $amount): PaymentPlan
    {
        $id = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'D'.PaymentPlan::count(), 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        return PaymentPlan::create([
            'no_transaksi' => 'PP-'.$id, 'id_divisi' => $id, 'tgl_pengajuan' => '2026-10-06',
            'jenis_transaksi' => 'BANK', 'vendor_toko' => 'Vendor', 'penerima_pj' => 'PIC',
            'rekening_va' => '123', 'keterangan' => 'Payment', 'nominal' => $amount,
            'nominal_aktual' => $amount, 'status_payment' => 'PAID', 'ref_bill_number' => 'BIL-AUDIT',
        ]);
    }

    public function test_partial_then_full_allocation_and_overpayment_guard(): void
    {
        $bill = PurchaseBill::create(['bill_number' => 'BIL-AUDIT', 'bill_date' => '2026-10-06', 'vendor_name' => 'Vendor', 'grand_total' => 100, 'journal_id' => 'JRN-BILL']);
        $service = app(BillPaymentAllocationService::class);
        $first = $this->payment(40);
        DB::transaction(fn () => $service->allocate($first, 'JRN-40'));
        $this->assertSame('PARTIAL', $bill->fresh()->payment_status);
        try {
            DB::transaction(fn () => $service->allocate($this->payment(61), 'JRN-61'));
            $this->fail('Overpayment accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_allocation_guard'), $e->getMessage());
        }
        DB::transaction(fn () => $service->allocate($this->payment(60), 'JRN-60'));
        $this->assertSame('PAID', $bill->fresh()->payment_status);
        $this->assertEquals(100, DB::table('bill_payment_allocations')->sum('amount'));
        $this->assertDatabaseCount('bill_payment_allocations', 2);
    }

    public function test_financial_actions_require_separate_finance_allowlists(): void
    {
        $middleware = new AuthorizePaymentAction;
        foreach (['STAFF', 'ADMIN', 'FINANCE'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $request = Request::create('/payment-plan', 'POST', ['status_payment' => 'PAID']);
            $request->setUserResolver(fn () => $user);
            config(['platform.payment_pay_user_ids' => $role === 'FINANCE' ? [] : [$user->id]]);
            try {
                $middleware->handle($request, fn () => 'allowed', 'status');
                $this->fail('Unauthorized payment accepted');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        config(['platform.payment_pay_user_ids' => [$user->id]]);
        $this->assertSame('allowed', $middleware->handle($request, fn () => 'allowed', 'status'));
    }
}
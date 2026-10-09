<?php

namespace Tests\Feature;

use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_reapproval_requires_independent_checker_and_preserves_paid_status(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('proof.pdf', 'proof');
        $maker = User::factory()->create(['role' => 'FINANCE']);
        $checker = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_approve_user_ids' => [$maker->id, $checker->id]]);
        foreach (['61100', '111101'] as $code) {
            \App\Models\Account::create(['account_code' => $code, 'account_name' => $code, 'normal_balance' => 'DEBET', 'coa_type' => $code === '111101' ? 'Cash & Bank' : 'EXPENSE', 'report_pos' => 'NERACA']);
        }
        $this->actingAs($maker);
        $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'MC3', 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        $payment = PaymentPlan::create(['no_transaksi' => 'PP-REAPPROVE', 'id_divisi' => $division, 'id_akun' => '61100', 'tgl_pengajuan' => '2026-10-08', 'tgl_transaksi' => '2026-10-08', 'jenis_transaksi' => '111101', 'kategori_payment' => 'OPERASIONAL', 'vendor_toko' => 'Vendor', 'penerima_pj' => 'PIC', 'rekening_va' => '-', 'keterangan' => 'Test', 'nominal' => 100, 'nominal_aktual' => 90, 'status_payment' => 'PAID']);
        $payment->details()->create(['keterangan' => 'Item', 'qty' => 1, 'nominal' => 100, 'nominal_aktual' => 90, 'bukti_file' => 'proof.pdf']);
        $payload = ['reason' => 'Bukti koreksi diperiksa ulang'];
        $this->postJson(route('payment.reapprove_paid', $payment->id_payment), $payload)->assertUnprocessable();
        $this->actingAs($checker)->post(route('payment.reapprove_paid', $payment->id_payment), $payload)->assertSessionHas('success');
        $this->assertSame('PAID', $payment->fresh()->status_payment);
        \App\Support\PaymentMakerChecker::verified($payment->fresh());
        $this->assertEquals($checker->id, $payment->fresh()->approver_user_id);
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('system_logs', 1);
        $payment->details()->update(['nominal_aktual' => 80]);
        $payment->refresh()->forceFill(['approval_fingerprint' => null, 'approver_user_id' => null, 'approved_at' => null])->save();
        $this->postJson(route('payment.reapprove_paid', $payment->id_payment), $payload)->assertUnprocessable();
        $this->assertNull($payment->fresh()->approver_user_id);
        $this->assertDatabaseCount('system_logs', 1);
    }

    public function test_maker_cannot_approve_but_different_allowlisted_checker_can(): void
    {
        $maker = User::factory()->create(['role' => 'FINANCE']);
        $checker = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_approve_user_ids' => [$maker->id, $checker->id]]);
        $this->actingAs($maker);
        $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'MC', 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        $payment = PaymentPlan::create(['no_transaksi' => 'PP-MC', 'id_divisi' => $division, 'tgl_pengajuan' => '2026-10-08', 'jenis_transaksi' => 'PENDING', 'kategori_payment' => 'OPERASIONAL', 'vendor_toko' => 'Vendor', 'penerima_pj' => 'PIC', 'rekening_va' => '-', 'keterangan' => 'Test', 'nominal' => 100, 'status_payment' => 'PENGAJUAN', 'maker_user_id' => $checker->id]);
        $this->assertEquals($maker->id, $payment->maker_user_id);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED', 'maker_user_id' => $checker->id])->assertUnprocessable();
        $this->assertSame('PENGAJUAN', $payment->fresh()->status_payment);
        $this->actingAs($checker)->post(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertSessionHas('success');
        $this->assertEquals($checker->id, $payment->fresh()->approver_user_id);
        $this->assertNotNull($payment->fresh()->approved_at);
        $this->assertEquals($maker->id, $payment->fresh()->maker_user_id);
        \App\Support\PaymentMakerChecker::verified($payment->fresh());
        $this->assertNotNull($payment->fresh()->approval_fingerprint);
        config(['platform.payment_account_user_ids' => [$checker->id]]);
        $this->post(route('payment.set_coa', $payment->id_payment), ['id_akun' => '61100'])->assertSessionHas('success');
        $this->assertSame('PENGAJUAN', $payment->fresh()->status_payment);
        $this->assertNull($payment->fresh()->approver_user_id);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertUnprocessable();
        $payment->forceFill(['maker_user_id' => null])->save();
        $this->actingAs($maker)->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertUnprocessable();
    }

    public function test_financial_mutation_and_unknown_approval_block_paid_and_posting(): void
    {
        $this->withoutMiddleware();
        $maker = User::factory()->create(['role' => 'FINANCE']);
        $checker = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($maker);
        $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'MC2', 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        $payment = PaymentPlan::create(['no_transaksi' => 'PP-PROOF', 'id_divisi' => $division, 'tgl_pengajuan' => '2026-10-08', 'jenis_transaksi' => 'PENDING', 'kategori_payment' => 'OPERASIONAL', 'vendor_toko' => 'Vendor', 'penerima_pj' => 'PIC', 'rekening_va' => '-', 'keterangan' => 'Test', 'nominal' => 100, 'status_payment' => 'PENGAJUAN']);
        $detail = $payment->details()->create(['keterangan' => 'Item', 'qty' => 1, 'nominal' => 100]);
        $this->actingAs($checker)->post(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertSessionHas('success');
        $detail->update(['nominal' => 99]);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable()->assertJsonValidationErrors('payment');
        $detail->update(['nominal' => 100]);
        \App\Support\PaymentMakerChecker::verified($payment->fresh());
        DB::table('transaksi_payment_plan')->where('id_payment', $payment->id_payment)->update(['nominal' => 101]);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable()->assertJsonValidationErrors('payment');
        $payment->refresh();
        $this->assertSame('APPROVED', $payment->status_payment);
        $payment->forceFill(['status_payment' => 'PAID'])->save();
        $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi])->assertSessionHas('error');
        $this->assertDatabaseCount('journal_headers', 0);
        $payment->forceFill(['status_payment' => 'APPROVED', 'approver_user_id' => null])->save();
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable();
        $this->assertDatabaseCount('journal_details', 0);
    }
}
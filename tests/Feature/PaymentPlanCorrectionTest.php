<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\PaymentPlan;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentPlanCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function payment(): PaymentPlan
    {
        $division = DB::table('master_divisi')->insertGetId([
            'kode_divisi' => 'FIN', 'nama_divisi' => 'Finance', 'status_aktif' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('accounts')->insert([
            'account_code' => '61100', 'account_name' => 'Biaya', 'normal_balance' => 'DEBET',
            'coa_type' => 'EXPENSE', 'report_pos' => 'LABA RUGI',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payment = PaymentPlan::create([
            'no_transaksi' => 'PP-CORRECTION', 'id_divisi' => $division,
            'tgl_pengajuan' => '2026-10-02', 'tgl_transaksi' => '2026-10-02',
            'jenis_transaksi' => 'BANK', 'vendor_toko' => 'Vendor', 'penerima_pj' => 'PIC',
            'rekening_va' => '123', 'keterangan' => 'Original', 'status_payment' => 'PAID',
            'nominal' => 100, 'nominal_aktual' => 90,
        ]);
        $payment->details()->create(['keterangan' => 'Item', 'nominal' => 100, 'nominal_aktual' => 90, 'bukti_file' => 'missing/old.pdf']);

        return $payment->refresh();
    }

    private function operator(): User
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_correction_user_ids' => [$user->id]]);
        $this->actingAs($user);

        return $user;
    }

    private function data(PaymentPlan $payment): array
    {
        return [
            'reason' => 'Bukti transfer diverifikasi Finance', 'id_akun' => '61100',
            'proofs' => [['id_detail' => $payment->details()->first()->id_detail, 'file' => UploadedFile::fake()->image('proof.jpg')]],
        ];
    }

    public function test_empty_allowlist_unlisted_finance_admin_and_staff_are_denied(): void
    {
        $payment = $this->payment();
        foreach (['FINANCE', 'ADMIN', 'STAFF'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach ([[], [$user->id]] as $list) {
                config(['platform.payment_correction_user_ids' => $list]);
                if ($role === 'FINANCE' && $list) {
                    config(['platform.payment_correction_user_ids' => [$user->id + 1000]]);
                }
                $this->get(route('payment.correction.edit', $payment->id_payment))->assertForbidden();
                $this->post(route('payment.correction.store', $payment->id_payment), ['reason' => 'Test'])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('system_logs', 0);
    }

    public function test_allowlisted_finance_can_correct_only_coa_and_missing_proof_with_audit(): void
    {
        Storage::fake('public');
        $payment = $this->payment();
        $user = $this->operator();
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('payment.correction.edit', $payment->id_payment))->assertOk()->assertSee(__('erp.payment_correction_title'));
        }
        $this->post(route('payment.correction.store', $payment->id_payment), $this->data($payment))->assertRedirect()->assertSessionHas('success');
        $payment->refresh();
        $this->assertSame('PAID', $payment->status_payment);
        $this->assertSame('61100', $payment->id_akun);
        $this->assertEquals(90, $payment->nominal_aktual);
        $this->assertSame('123', $payment->rekening_va);
        Storage::disk('public')->assertExists($payment->details()->first()->bukti_file);
        $log = DB::table('system_logs')->first();
        $this->assertEquals($user->id, $log->user_id);
        $this->assertStringContainsString('missing/old.pdf', $log->description);
        $this->assertStringContainsString('sha256', $log->description);
        $this->assertStringContainsString('Bukti transfer diverifikasi Finance', $log->description);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_forbidden_fields_missing_reason_and_invalid_coa_make_no_changes(): void
    {
        Storage::fake('public');
        $payment = $this->payment();
        $this->operator();
        foreach ([['nominal_aktual' => 80], ['reason' => ''], ['id_akun' => 'MISSING']] as $extra) {
            $this->postJson(route('payment.correction.store', $payment->id_payment), array_replace($this->data($payment), $extra))->assertUnprocessable();
        }
        $this->assertNull($payment->fresh()->id_akun);
        $this->assertSame('missing/old.pdf', $payment->details()->first()->bukti_file);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_existing_evidence_and_other_payment_details_cannot_be_overwritten(): void
    {
        Storage::fake('public');
        $payment = $this->payment();
        $this->operator();
        Storage::disk('public')->put('missing/old.pdf', 'original');
        $this->postJson(route('payment.correction.store', $payment->id_payment), $this->data($payment))->assertUnprocessable();
        $this->assertSame('original', Storage::disk('public')->get('missing/old.pdf'));
        $data = $this->data($payment);
        $data['proofs'][0]['id_detail'] = 99999;
        $this->postJson(route('payment.correction.store', $payment->id_payment), $data)->assertUnprocessable();
        $this->assertNull($payment->fresh()->id_akun);
        $this->assertDatabaseCount('system_logs', 0);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_posted_or_journaled_or_unpaid_documents_are_rejected(): void
    {
        Storage::fake('public');
        $payment = $this->payment();
        $this->operator();
        foreach (['POSTED', 'PENGAJUAN'] as $status) {
            $payment->update(['status_payment' => $status]);
            $this->postJson(route('payment.correction.store', $payment->id_payment), $this->data($payment))->assertUnprocessable();
        }
        $payment->update(['status_payment' => 'PAID']);
        JournalHeader::create([
            'journal_id' => JournalHeader::idForPaymentPlan($payment->no_transaksi),
            'transaction_date' => '2026-10-02', 'source_doc_no' => $payment->no_transaksi,
            'transaction_type' => 'Payment Plan',
        ]);
        $this->postJson(route('payment.correction.store', $payment->id_payment), $this->data($payment))->assertUnprocessable();
        $this->assertNull($payment->fresh()->id_akun);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('journal_headers', 1);
    }

    public function test_audit_failure_rolls_back_data_and_removes_only_new_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('unrelated.pdf', 'keep');
        $payment = $this->payment();
        $this->operator();
        SystemLog::creating(function () {
            throw new \RuntimeException('Audit storage unavailable');
        });
        try {
            $this->post(route('payment.correction.store', $payment->id_payment), $this->data($payment))->assertStatus(500);
            $this->assertNull($payment->fresh()->id_akun);
            $this->assertSame('missing/old.pdf', $payment->details()->first()->bukti_file);
            $this->assertSame(['unrelated.pdf'], Storage::disk('public')->allFiles());
        } finally {
            SystemLog::flushEventListeners();
        }
    }
}

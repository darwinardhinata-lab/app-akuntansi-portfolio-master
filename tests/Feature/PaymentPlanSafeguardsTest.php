<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\PaymentPlan;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PaymentPlanSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    private function payment(string $status = 'PENGAJUAN'): PaymentPlan
    {
        $division = DB::table('master_divisi')->insertGetId([
            'kode_divisi' => 'T'.DB::table('master_divisi')->count(),
            'nama_divisi' => 'Test Finance', 'status_aktif' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payment = PaymentPlan::create([
            'no_transaksi' => 'TEST-PP-'.$division, 'id_divisi' => $division,
            'tgl_pengajuan' => '2026-10-02', 'tgl_transaksi' => '2026-10-02',
            'jenis_transaksi' => 'BANK', 'kategori_payment' => 'OPERASIONAL',
            'vendor_toko' => 'VENDOR', 'penerima_pj' => 'PIC', 'rekening_va' => '123',
            'keterangan' => 'Test', 'nominal' => 100, 'nominal_aktual' => 90,
            'status_payment' => $status,
        ]);
        $payment->details()->create([
            'keterangan' => 'Original', 'qty' => 1, 'nominal' => 100,
            'nominal_aktual' => 90, 'bukti_file' => 'proof/test.pdf',
        ]);

        return $payment->refresh();
    }

    private function payload(PaymentPlan $payment): array
    {
        return [
            'id_divisi' => $payment->id_divisi, 'tgl_pengajuan' => '2026-10-02',
            'tgl_transaksi' => '2026-10-02', 'jenis_transaksi' => 'PENDING',
            'kategori_payment' => 'OPERASIONAL', 'vendor_toko' => 'CHANGED',
            'penerima_pj' => 'PIC', 'rekening_va' => '999',
            'items' => [[
                'id_detail' => $payment->details()->first()->id_detail,
                'keterangan' => 'Changed', 'qty' => 1, 'nominal' => 100,
                'nominal_aktual' => 80,
            ]],
        ];
    }

    public function test_paid_and_posted_payments_cannot_be_edited_deleted_or_downgraded(): void
    {
        $this->withoutMiddleware();
        Storage::fake('public');
        Storage::disk('public')->put('proof/test.pdf', 'original');

        foreach (['PAID', 'POSTED'] as $status) {
            $payment = $this->payment($status);
            $original = $payment->getAttributes();
            $detail = $payment->details()->first()->getAttributes();
            $this->put(route('payment.update', $payment->id_payment), $this->payload($payment))
                ->assertRedirect()->assertSessionHas('error');
            $this->delete(route('payment.destroy', $payment->id_payment))
                ->assertRedirect()->assertSessionHas('error');
            foreach ([
                'payment.set_coa' => ['id_akun' => '999'],
                'payment.set_rekening' => ['jenis_transaksi' => 'KAS'],
                'payment.update_status' => ['status_payment' => 'PENGAJUAN'],
            ] as $route => $data) {
                $this->postJson(route($route, $payment->id_payment), $data)->assertUnprocessable();
            }
            $this->assertSame($original, $payment->fresh()->getAttributes());
            $this->assertSame($detail, $payment->details()->first()->getAttributes());
            Storage::disk('public')->assertExists('proof/test.pdf');
        }
    }

    public function test_posted_cannot_be_assigned_manually_but_ordinary_approval_still_works(): void
    {
        $this->withoutMiddleware();
        $maker = \App\Models\User::factory()->create();
        $checker = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($maker);
        $payment = $this->payment();
        $this->actingAs($checker);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'POSTED'])
            ->assertUnprocessable()->assertJsonValidationErrors('status_payment');
        $this->assertSame('PENGAJUAN', $payment->fresh()->status_payment);
        $this->post(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('APPROVED', $payment->fresh()->status_payment);
    }

    public function test_existing_journal_protects_payment_even_when_status_is_inconsistent(): void
    {
        $this->withoutMiddleware();
        $payment = $this->payment();
        $journal = JournalHeader::create([
            'journal_id' => JournalHeader::idForPaymentPlan($payment->no_transaksi),
            'transaction_date' => '2026-10-02', 'source_doc_no' => $payment->no_transaksi,
            'transaction_type' => 'Payment Plan',
        ]);
        $this->delete(route('payment.destroy', $payment->id_payment))->assertSessionHas('error');
        $this->put(route('payment.update', $payment->id_payment), $this->payload($payment))->assertSessionHas('error');
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertUnprocessable();
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $journal->getKey()]);
        $this->assertDatabaseHas('transaksi_payment_plan', ['id_payment' => $payment->id_payment]);
    }

    public function test_received_synthetic_and_referenced_orders_are_preserved(): void
    {
        $this->withoutMiddleware();
        foreach (['synthetic', 'reference'] as $mode) {
            $payment = $this->payment();
            $number = $mode === 'synthetic' ? 'PO-'.$payment->no_transaksi : 'PO-REAL-'.$payment->id_payment;
            if ($mode === 'reference') {
                $payment->update(['ref_po_number' => $number]);
            }
            $order = PurchaseOrder::create([
                'po_number' => $number, 'transaction_date' => '2026-10-02',
                'contact_name' => 'VENDOR', 'status' => 'APPROVED',
            ]);
            $order->details()->create([
                'item_code' => 'SKU', 'description' => 'Item', 'qty' => 2,
                'qty_received' => 1, 'price' => 50, 'amount' => 100,
            ]);
            $this->delete(route('payment.destroy', $payment->id_payment))->assertSessionHas('error');
            $this->put(route('payment.update', $payment->id_payment), $this->payload($payment))->assertSessionHas('error');
            $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
            $this->assertDatabaseHas('purchase_order_details', ['purchase_order_id' => $order->id, 'qty_received' => 1]);
            $this->assertDatabaseHas('transaksi_payment_plan', ['id_payment' => $payment->id_payment]);
        }
    }

    public function test_unpaid_unjournaled_payment_can_still_be_edited_and_deleted(): void
    {
        $this->withoutMiddleware();
        Storage::fake('public');
        $payment = $this->payment();
        $this->put(route('payment.update', $payment->id_payment), $this->payload($payment))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('CHANGED', $payment->fresh()->vendor_toko);
        $this->assertEquals(80, $payment->fresh()->nominal_aktual);
        $this->delete(route('payment.destroy', $payment->id_payment))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('transaksi_payment_plan', ['id_payment' => $payment->id_payment]);
        $this->assertDatabaseMissing('transaksi_payment_plan_detail', ['id_payment' => $payment->id_payment]);
    }

    public function test_csv_cannot_create_posted_status_without_a_journal(): void
    {
        $this->withoutMiddleware();
        $this->payment();
        $csv = "NO PP;TANGGAL;KATEGORI;REKENING;STATUS;PIC;DIVISI;VENDOR;LINK;KETERANGAN;VA;COA;QTY;SATUAN;NOMINAL;AKTUAL\n"
            ."CSV-POSTED;2026-10-02;OPERASIONAL;BANK;POSTED;PIC;;VENDOR;;Test;123;;1;Pcs;100;90\n";
        $file = UploadedFile::fake()->createWithContent('payment.csv', $csv);
        $this->post(route('payment.import'), ['file_csv' => $file])
            ->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, 'GAGAL: 1'));
        $this->assertDatabaseMissing('transaksi_payment_plan', ['no_transaksi' => 'CSV-POSTED']);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_legacy_receiving_status_and_grn_mode_block_payment_deletion(): void
    {
        $this->withoutMiddleware();
        foreach (['PARTIAL', 'RECEIVED', 'PARTIAL_RECEIVED', 'FULLY_RECEIVED', 'GRN_V1'] as $marker) {
            $payment = $this->payment();
            $order = PurchaseOrder::create([
                'po_number' => 'PO-'.$payment->no_transaksi, 'transaction_date' => '2026-10-02',
                'contact_name' => 'VENDOR', 'status' => $marker === 'GRN_V1' ? 'APPROVED' : $marker,
            ]);
            if ($marker === 'GRN_V1') {
                $order->forceFill(['receipt_mode' => 'GRN_V1'])->save();
            }
            $this->delete(route('payment.destroy', $payment->id_payment))->assertSessionHas('error');
            $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
            $this->assertDatabaseHas('transaksi_payment_plan', ['id_payment' => $payment->id_payment]);
        }
    }

    public function test_locked_edit_form_renders_read_only_in_supported_locales(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);
        $payment = $this->payment('PAID');
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('payment.edit', $payment->id_payment))
                ->assertOk()->assertSee(__('erp.payment_locked_guard'))
                ->assertSee('<fieldset disabled>', false);
        }
    }

    private function readyPayment(): PaymentPlan
    {
        Storage::fake('public');
        Storage::disk('public')->put('proof/test.pdf', 'proof');
        foreach ([['61100', 'Biaya', 'DEBET'], ['111101', 'Mandiri IDR', 'DEBET']] as [$code, $name, $balance]) {
            DB::table('accounts')->updateOrInsert(['account_code' => $code], [
                'account_name' => $name, 'normal_balance' => $balance,
                'coa_type' => $code === '111101' ? 'Cash & Bank' : 'EXPENSE', 'report_pos' => 'NERACA',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $maker = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        $checker = \App\Models\User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($maker);
        $payment = $this->payment('PENGAJUAN');
        $payment->update(['id_akun' => '61100', 'jenis_transaksi' => '111101']);
        $this->actingAs($checker);
        \App\Support\PaymentMakerChecker::approve($payment->refresh());
        $payment->update(['status_payment' => 'APPROVED']);

        return $payment->refresh();
    }

    public function test_paid_requires_approval_and_complete_realization(): void
    {
        $this->withoutMiddleware();
        $payment = $this->readyPayment();
        $payment->update(['status_payment' => 'PENGAJUAN']);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable();
        $payment->update(['status_payment' => 'APPROVED']);
        Storage::disk('public')->delete('proof/test.pdf');
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable();
        $this->assertSame('APPROVED', $payment->fresh()->status_payment);
        Storage::disk('public')->put('proof/test.pdf', 'proof');
        $this->post(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertSessionHas('success');
        $this->assertSame('PAID', $payment->fresh()->status_payment);
        $this->assertDatabaseHas('system_logs', ['module' => 'Payment Plan', 'action' => 'UPDATE']);
    }

    public function test_missing_actual_and_amount_above_request_are_rejected(): void
    {
        $this->withoutMiddleware();
        $payment = $this->readyPayment();
        foreach ([null, 101, 90.555] as $amount) {
            $payment->details()->update(['nominal_aktual' => $amount]);
            $payment->forceFill(['nominal_aktual' => $amount])->save();
            $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertUnprocessable();
            $this->assertSame('APPROVED', $payment->fresh()->status_payment);
        }
    }

    public function test_rejected_cannot_be_reactivated_and_approved_edits_require_reapproval(): void
    {
        $this->withoutMiddleware();
        $payment = $this->readyPayment();
        $payment->update(['status_payment' => 'REJECTED']);
        $this->postJson(route('payment.update_status', $payment->id_payment), ['status_payment' => 'APPROVED'])->assertUnprocessable();
        $payment->update(['status_payment' => 'APPROVED']);
        $this->put(route('payment.update', $payment->id_payment), $this->payload($payment))->assertSessionHas('success');
        $this->assertSame('PENGAJUAN', $payment->fresh()->status_payment);
        foreach (['payment.set_coa' => ['id_akun' => '61100'], 'payment.set_rekening' => ['jenis_transaksi' => '111101']] as $route => $data) {
            $payment->update(['status_payment' => 'APPROVED']);
            $this->post(route($route, $payment->id_payment), $data)->assertSessionHas('success');
            $this->assertSame('PENGAJUAN', $payment->fresh()->status_payment);
        }
    }

    public function test_confirmed_payment_posts_once_with_journal_linkage_and_actual_amount(): void
    {
        $this->withoutMiddleware();
        $payment = $this->readyPayment();
        $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi])->assertSessionHas('error');
        $this->assertDatabaseCount('journal_headers', 0);
        $this->post(route('payment.update_status', $payment->id_payment), ['status_payment' => 'PAID'])->assertSessionHas('success');
        $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi])->assertSessionHas('success');
        $payment->refresh();
        $this->assertSame('POSTED', $payment->status_payment);
        $this->assertSame(JournalHeader::idForPaymentPlan($payment->no_transaksi), $payment->journal_id);
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $payment->journal_id, 'position' => 'DEBET', 'amount' => 90]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $payment->journal_id, 'position' => 'KREDIT', 'amount' => 90]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $payment->journal_id, 'position' => 'KREDIT', 'account_code' => '111101', 'amount' => 90]);
        $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi])->assertSessionHas('error');
        $this->assertDatabaseCount('journal_headers', 1);
    }

    public function test_paid_without_realization_proof_cannot_post(): void
    {
        $this->withoutMiddleware();
        $payment = $this->readyPayment();
        $payment->update(['status_payment' => 'PAID']);
        Storage::disk('public')->delete('proof/test.pdf');
        $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi])->assertSessionHas('error');
        $this->assertSame('PAID', $payment->fresh()->status_payment);
        $this->assertDatabaseCount('journal_headers', 0);
    }
}

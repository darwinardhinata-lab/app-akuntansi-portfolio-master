<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function reconciliationAction(array $updates, string $method, array $params): void
    {
        app('livewire')->flushState();
        $this->flushHeaders();
        $html = $this->get('/erp-panel/ap-reconciliation')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $candidate = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            if (($decoded['memo']['path'] ?? '') === 'erp-panel/ap-reconciliation'
                && array_key_exists('proposal', $decoded['data'] ?? [])) $snapshot = $candidate;
        }
        $this->assertNotNull($snapshot);
        $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            'components' => [['snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]]]],
        ])->assertOk()->assertJsonMissingPath('components.0.effects.errors');
    }

    public function test_return_allocation_across_invoices_requires_full_quantity_and_preserves_journal(): void
    {
        config(['platform.filament_enabled' => true, 'payment_plan_v33.bank_workflow' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'RECON', 'name' => 'Reconciliation']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'ap.reconcile.view', 'ap.reconcile.propose', 'ap.reconcile.approve', 'ap.reconcile.cancel'])->pluck('id'));
        $users = [User::factory()->create(), User::factory()->create()];
        foreach ($users as $user) {
            $user->companies()->attach($company->id, ['is_default' => true]);
            $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        foreach (['211001', '111003', '610001'] as $code) DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $code, 'coa_type' => 'Other', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        $po = DB::table('purchase_orders')->insertGetId(['po_number' => 'PO-RETURN-TEST', 'transaction_date' => '2026-01-01', 'contact_name' => 'Synthetic']);
        $bills = [];
        foreach (['A', 'B'] as $suffix) {
            $source = \App\Support\PostingService::post(['source_doc_no' => 'BILL-'.$suffix], [
                ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '200.00'],
            ]);
            $bills[] = DB::table('purchase_bills')->insertGetId(['bill_number' => 'BILL-'.$suffix, 'bill_date' => '2026-01-01', 'vendor_name' => 'Synthetic', 'credit_account' => '211001', 'grand_total' => '200.00', 'journal_id' => $source->getKey(), 'purchase_order_id' => $po]);
        }
        $return = DB::table('purchase_returns')->insertGetId(['return_number' => 'PR-TEST', 'purchase_order_id' => $po, 'return_date' => '2026-01-02', 'total_return_amount' => '50.00']);
        $detail = DB::table('purchase_return_details')->insertGetId(['purchase_return_id' => $return, 'item_code' => 'SYNTHETIC', 'qty_returned' => 5, 'price' => '10.00', 'subtotal' => '50.00']);
        $journal = \App\Support\PostingService::post(['source_doc_no' => 'PR-TEST'], [
            ['account_code' => '211001', 'position' => 'DEBET', 'amount' => '50.00'], ['account_code' => '610001', 'position' => 'KREDIT', 'amount' => '50.00'],
        ]);
        $count = DB::table('journal_headers')->count();
        $this->actingAs($users[0])->withSession([\App\Modules\Platform\Support\CompanyContext::SESSION_KEY => $company->id]);
        $this->get('/erp-panel/ap-reconciliation')->assertOk();
        // Livewire's isolated test route omits StartSession; preserve the ERP session context.
        $this->app->rebinding('request', function ($app, $request) {
            if (!$request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('erp'));
        $service = new ApReconciliation();
        $input = ['purchase_bill_id' => $bills[0], 'journal_id' => $journal->getKey(), 'amount' => '30.00', 'reason' => 'Verified return receipt', 'evidence' => 'INTERNAL-RETURN-1'];
        $this->reconciliationAction(['proposal.purchase_bill_id' => (string) $bills[0], 'invoiceSearch' => 'BILL-A',
            'journalSearch' => 'PR-TEST', 'returnSearch' => 'SYNTHETIC'], '$refresh', []);
        $preview = DB::transaction(fn () => $service->previewSource($journal->getKey(), \App\Models\PurchaseBill::findOrFail($bills[0]), $detail));
        $this->assertSame('50.00', $preview['remaining']);
        try { $service->propose($input); $this->fail('Return bypass'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('return_detail_allocation_required', $e->errors()['reconciliation'][0]); }
        $input += ['purchase_return_detail_id' => $detail, 'quantity' => '3'];
        $this->reconciliationAction(['proposal' => $input], 'propose', []);
        $id = (int) DB::table('ppl_ap_reconciliations')->latest('id')->value('id');
        $this->actingAs($users[1]);
        $this->reconciliationAction(['decisionReason' => 'Verified independently'], 'decide', [$id, 1, 'approve']);
        $projection = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bills[0], $company->id));
        $this->assertSame('170.00', $projection['residual']);
        $this->assertSame('ap_reconciliation_required', $projection['blocked']);
        $this->assertSame('30.00', $projection['exceptions'][0]['allocated']);
        $this->actingAs($users[0]);
        $second = $service->propose(array_replace($input, ['purchase_bill_id' => $bills[1], 'quantity' => '2', 'amount' => '20.00']));
        $this->actingAs($users[1]);
        $service->decide($second, 1, 'approve', 'Second invoice allocation');
        $preview = DB::transaction(fn () => $service->previewSource($journal->getKey(), \App\Models\PurchaseBill::findOrFail($bills[0]), $detail));
        $this->assertSame('0.00', $preview['remaining']);
        foreach ([$bills[0] => '170.00', $bills[1] => '180.00'] as $bill => $residual) {
            $projection = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
            $this->assertSame($residual, $projection['residual']);
            $this->assertEmpty($projection['exceptions']);
        }
        $this->assertSame($count, DB::table('journal_headers')->count());
        $this->actingAs($users[0]);
        $third = $service->propose(array_replace($input, ['quantity' => '1', 'amount' => '10.00']));
        $this->actingAs($users[1]);
        try { $service->decide($third, 1, 'approve', 'Reject duplicate quantity'); $this->fail('Double allocation'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('source_overallocated', $e->errors()['reconciliation'][0]); }
        $service->decide($second, 2, 'cancel', 'Correct invoice attribution');
        $projection = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bills[1], $company->id));
        $this->assertSame('200.00', $projection['residual']);
        $this->assertNotEmpty($projection['exceptions']);
        DB::table('purchase_return_details')->where('id', $detail)->update(['qty_returned' => 6]);
        $projection = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bills[0], $company->id));
        $this->assertSame('approved_source_changed', $projection['exceptions'][0]['reason']);
        $this->assertSame('200.00', $projection['residual']);
    }

    public function test_finance_can_resolve_partial_journal_identity_with_audit_and_capacity_controls(): void
    {
        config(['platform.filament_enabled' => true, 'payment_plan_v33.bank_workflow' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'RECON', 'name' => 'Reconciliation']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access','ap.reconcile.view','ap.reconcile.propose','ap.reconcile.approve','ap.reconcile.cancel'])->pluck('id'));
        $users = [User::factory()->create(), User::factory()->create()];
        foreach ($users as $user) {
            $user->companies()->attach($company->id, ['is_default' => true]);
            $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        foreach (['211001','111003','610001'] as $code) DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $code, 'coa_type' => 'Other', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        $journal = \App\Support\PostingService::post(['source_doc_no' => 'BILL-RECON'], [
            ['account_code' => '610001','position' => 'DEBET','amount' => '200.00'], ['account_code' => '211001','position' => 'KREDIT','amount' => '200.00'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'BILL-RECON','bill_date' => '2026-01-01','vendor_name' => 'Synthetic','credit_account' => '211001','grand_total' => '200.00','journal_id' => $journal->getKey()]);
        $payment = \App\Support\PostingService::post(['source_doc_no' => 'BILL-RECON'], [
            ['account_code' => '211001','position' => 'DEBET','amount' => '50.00'], ['account_code' => '111003','position' => 'KREDIT','amount' => '50.00'],
        ]);
        $this->actingAs($users[0])->withSession([\App\Modules\Platform\Support\CompanyContext::SESSION_KEY => $company->id]);
        $this->get('/erp-panel/ap-reconciliation')->assertOk();
        $service = new ApReconciliation();
        $id = $service->propose(['purchase_bill_id' => $bill,'journal_id' => $payment->getKey(),'amount' => '30.00','reason' => 'Verified supplier receipt','evidence' => 'INTERNAL-RECEIPT-1']);
        try { $service->decide($id, 1, 'approve', 'Verified approval'); $this->fail('Self approval'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('independent_approval_required', $e->errors()['reconciliation'][0]); }
        $this->actingAs($users[1]);
        $service->decide($id, 1, 'approve', 'Verified independently');
        $source = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('170.00', $source['residual']);
        $this->assertEmpty($source['exceptions']);
        $this->assertDatabaseCount('ppl_ap_reconciliation_decisions', 2);
        DB::table('journal_headers')->where('journal_id', $payment->getKey())->update(['notes' => 'Source changed']);
        $changed = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('approved_source_changed', $changed['exceptions'][0]['reason']);
        $this->assertSame('200.00', $changed['residual']);
        DB::table('journal_headers')->where('journal_id', $payment->getKey())->update(['notes' => null]);
        try { $service->decide($id, 1, 'approve', 'Retry stale decision'); $this->fail('Stale approval'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('stale_version', $e->errors()['reconciliation'][0]); }
        $this->actingAs($users[0]);
        $second = $service->propose(['purchase_bill_id' => $bill,'journal_id' => $payment->getKey(),'amount' => '25.00','reason' => 'Second partial allocation','evidence' => 'INTERNAL-RECEIPT-1']);
        $this->actingAs($users[1]);
        try { $service->decide($second, 1, 'approve', 'Reject excess allocation'); $this->fail('Source capacity exceeded'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('source_overallocated', $e->errors()['reconciliation'][0]); }
        $this->actingAs($users[0]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $private = app(\App\Modules\PaymentPlan\Services\PrivateAttachmentStore::class);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nsynthetic receipt");
        $private->reconciliation($second, 1, $file);
        $attachment = DB::table('ppl_attachments')->where('reconciliation_id', $second)->first();
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $attachment->sha256);
        $this->assertNull($attachment->request_id);
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'action' => 'upload']);
        $this->get(route('payment-plan-v33.download', ['id' => $attachment->id, 'preview' => 1]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
        $this->get(route('payment-plan-v33.download', $attachment->id))->assertOk();
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'action' => 'preview']);
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'action' => 'download']);
        \Illuminate\Support\Facades\Storage::disk('local')->put($attachment->storage_key, 'tampered');
        $this->get(route('payment-plan-v33.download', $attachment->id))->assertStatus(409);
        try { $private->reconciliation($second, 1, $file); $this->fail('Stale attachment version accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        try { $private->reconciliation($second, 2, $file); $this->fail('Duplicate attachment accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        try { $private->reconciliation($second, 2, \Illuminate\Http\UploadedFile::fake()->createWithContent('script.html', '<html>untrusted</html>')); $this->fail('HTML upload accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->actingAs($users[1]);
        try { $private->reconciliation($second, 2, archive: $attachment->id); $this->fail('Non-uploader archive accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->actingAs($users[0]);
        $private->reconciliation($second, 2, archive: $attachment->id);
        $this->get(route('payment-plan-v33.download', $attachment->id))->assertForbidden();
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'action' => 'archive']);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment->storage_key);
        \Illuminate\Support\Facades\Storage::disk('local')->put($attachment->storage_key, "%PDF-1.4\nsynthetic receipt");
        $role->permissions()->attach(Permission::where('code', 'ap.reconcile.audit')->value('id'));
        $this->actingAs($users[1]);
        $this->get(route('payment-plan-v33.download', ['id' => $attachment->id, 'preview' => 1]))->assertOk()
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'action' => 'archived_preview']);
        $this->actingAs($users[1]);
        $service->decide($id, 2, 'cancel', 'Correct mistaken attribution');
        $source = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('200.00', $source['residual']);
        $this->assertSame('ap_reconciliation_required', $source['blocked']);
        $role->permissions()->detach(Permission::where('code','ap.reconcile.propose')->value('id'));
        try { $service->propose([]); $this->fail('Revoked permission'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        foreach (range(1, 21) as $index) {
            DB::table('purchase_bills')->insert(['bill_number' => 'PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'bill_date' => '2026-01-01', 'vendor_name' => 'Search supplier', 'credit_account' => '211001',
                'grand_total' => '200.00', 'journal_id' => $journal->getKey()]);
        }
        $this->get('/erp-panel/ap-reconciliation?invoices=2')->assertOk()->assertSee('PAGE-21')->assertDontSee('PAGE-01');
        $role->permissions()->detach(Permission::where('code','ap.reconcile.view')->value('id'));
        try {
            DB::transaction(fn () => $service->previewSource($payment->getKey(), \App\Models\PurchaseBill::findOrFail($bill)));
            $this->fail('Revoked lookup permission');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->get('/erp-panel/ap-reconciliation')->assertForbidden();
    }
}
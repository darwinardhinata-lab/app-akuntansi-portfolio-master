<?php

namespace Tests\Support;

use App\Models\PurchaseBill;
use App\Models\User;
use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\ApResidualProjection;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\PostingService;
use Database\Seeders\PlatformPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared HTTP posting / authenticated application-use-case tests, including disposable MariaDB. */
trait SupplierCreditSourceCases
{
    private function creditFixture(): array
    {
        config(['platform.filament_enabled' => true, 'platform.order_company_scope_enabled' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false,
            'platform.posting_intents_enabled' => true, 'payment_plan_v33.bank_workflow' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'Credit source fixture', 'active' => true, 'base_currency' => 'IDR']);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        $this->seed(PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'CREDIT_SOURCE_TEST', 'name' => 'Synthetic']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'supplier_credit.post', 'ap.reconcile.view', 'ap.reconcile.propose', 'ap.reconcile.approve'])->pluck('id'));
        $finance = User::factory()->create(['role' => 'FINANCE']);
        $approver = User::factory()->create();
        foreach ([$finance, $approver] as $user) {
            $user->companies()->attach($company->id, ['is_default' => true]);
            $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        $party = Party::create(['company_id' => $company->id, 'code' => 'SOURCE-SUP', 'legal_name' => 'Synthetic supplier', 'active' => true]);
        $party->roles()->create(['role' => 'SUPPLIER', 'active' => true]);
        $otherParty = Party::create(['company_id' => $company->id, 'code' => 'OTHER-SUP', 'legal_name' => 'Other supplier', 'active' => true]);
        $otherParty->roles()->create(['role' => 'SUPPLIER', 'active' => true]);
        $po = DB::table('purchase_orders')->insertGetId(['company_id' => $company->id, 'party_id' => $party->id,
            'po_number' => 'SOURCE-PO', 'transaction_date' => '2026-01-01', 'contact_name' => 'Synthetic', 'receipt_mode' => 'GRN_V1']);
        $otherPo = DB::table('purchase_orders')->insertGetId(['company_id' => $company->id, 'party_id' => $otherParty->id,
            'po_number' => 'OTHER-PO', 'transaction_date' => '2026-01-01', 'contact_name' => 'Synthetic']);
        foreach (['510003', '610001', '211001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code,
                'account_name' => 'Synthetic', 'coa_type' => 'Other', 'report_pos' => 'NERACA', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET']);
        }
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'AP_SUPPLIER_PRICE_DISCOUNT_CREDIT_NOTE', 'account_code' => '510003', 'active' => true]);
        $journal = PostingService::post(['source_doc_no' => 'SOURCE-BILL', 'transaction_date' => '2026-01-01', 'currency_code' => 'IDR'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.50'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '200.50'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['purchase_order_id' => $po, 'bill_number' => 'SOURCE-BILL',
            'bill_date' => '2026-01-01', 'vendor_name' => 'Synthetic', 'credit_account' => '211001', 'grand_total' => '200.50', 'journal_id' => $journal->getKey()]);
        $receipt = DB::table('purchase_receipts')->insertGetId(['company_id' => $company->id, 'purchase_order_id' => $po,
            'party_id' => $party->id, 'receipt_number' => 'SOURCE-GRN', 'receipt_date' => '2026-01-01', 'status' => 'POSTED',
            'journal_id' => $journal->getKey(), 'purchase_bill_id' => $bill]);
        $url = route('purchase-bills.supplier-credit', $bill);
        $payload = ['supplier_reference' => 'CN-SOURCE', 'posting_date' => '2026-01-02', 'amount' => '20.00', 'reason' => 'Synthetic supplier allowance'];

        return compact('company', 'finance', 'approver', 'party', 'otherParty', 'po', 'otherPo', 'journal', 'bill', 'receipt', 'url', 'payload');
    }

    private function creditActor(array $f, bool $approval = false): void
    {
        $this->actingAs($approval ? $f['approver'] : $f['finance'])->withSession([CompanyContext::SESSION_KEY => $f['company']->id]);
        if (! request()->hasSession()) {
            request()->setLaravelSession(app('session')->driver());
        }
    }

    private function creditSnapshot(array $f): array
    {
        $state = [];
        foreach (['journal_headers', 'journal_details', 'accounting_posting_intents', 'supplier_credit_bindings',
            'ppl_ap_reconciliations', 'ppl_ap_reconciliation_decisions', 'ppl_requests', 'ppl_events', 'ppl_reservations',
            'ppl_ap_allocations', 'ppl_payment_events', 'transaksi_payment_plan', 'bill_payment_allocations',
            'purchase_orders', 'purchase_receipts', 'purchase_bills', 'inventory_ledgers'] as $table) {
            $state[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->sortBy(fn ($r) => json_encode($r))->values()->all();
        }
        $state['projection'] = app(ApResidualProjection::class)->project(PurchaseBill::findOrFail($f['bill']), '211001', '200.50');

        return $state;
    }

    private function creditProposal(array $f): array
    {
        $this->creditActor($f);
        $binding = $this->postJson($f['url'], $f['payload'])->assertOk()->json();
        $this->creditActor($f);
        $id = app(ApReconciliation::class)->propose(['purchase_bill_id' => $f['bill'], 'journal_id' => $binding['journal_id'],
            'amount' => '20.00', 'reason' => 'Source verification proposal', 'evidence' => 'CN-SOURCE']);
        $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);

        return [$binding, $id];
    }

    private function rejectedApproval(array $f, int $id, string $expected): void
    {
        $this->creditActor($f, true);
        $before = $this->creditSnapshot($f);
        try {
            app(ApReconciliation::class)->decide($id, 1, 'approve', 'Independent source verification');
            $this->fail('Invalid credit source approved: '.$expected);
        } catch (ValidationException $e) {
            $this->assertSame($expected, $e->errors()['reconciliation'][0]);
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame($expected, $e->getMessage());
        }
        $this->unchangedCreditState($f, $before, 'approval:'.$expected);
        $this->assertSame('proposed', DB::table('ppl_ap_reconciliations')->where('id', $id)->value('status'));
    }

    private function unchangedCreditState(array $f, array $before, string $case): void
    {
        $after = $this->creditSnapshot($f);
        $this->assertSame($before, $after, $case.': rejection must not mutate financial/business evidence');
        $directory = getenv('SUPPLIER_CREDIT_EVIDENCE_DIR');
        if ($directory && DB::connection()->getDriverName() === 'mysql') {
            $this->assertMatchesRegularExpression('/\Aaudit_ap_test_[a-f0-9]{16}\z/', DB::connection()->getDatabaseName());
            file_put_contents($directory.'/rejections.jsonl', json_encode(['test' => $this->name(), 'case' => $case,
                'database' => DB::connection()->getDatabaseName(), 'before' => $before, 'after' => $after], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    public function test_credit_source_rejects_foreign_bill_and_foreign_binding_without_partial_writes(): void
    {
        $f = $this->creditFixture();
        [$binding, $id] = $this->creditProposal($f);
        $foreign = Company::create(['code' => 'OTHER', 'name' => 'Foreign', 'active' => true, 'base_currency' => 'IDR']);
        DB::table('purchase_orders')->where('id', $f['po'])->update(['company_id' => $foreign->id]);
        $this->creditActor($f);
        $before = $this->creditSnapshot($f);
        $this->postJson($f['url'], [...$f['payload'], 'supplier_reference' => 'CN-FOREIGN'])->assertConflict();
        $this->unchangedCreditState($f, $before, 'foreign-bill:posting');
        $this->creditActor($f, true);
        try {
            app(ApReconciliation::class)->decide($id, 1, 'approve', 'Foreign bill must fail');
            $this->fail('Foreign company accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->unchangedCreditState($f, $before, 'foreign-bill:approval');
        DB::table('supplier_credit_bindings')->where('id', $binding['id'])->update(['company_id' => $foreign->id]);
        DB::table('purchase_orders')->where('id', $f['po'])->update(['company_id' => $f['company']->id]);
        $before = $this->creditSnapshot($f);
        $this->creditActor($f);
        $this->postJson($f['url'], $f['payload'])->assertConflict();
        $this->unchangedCreditState($f, $before, 'foreign-binding:posting');
        $this->creditActor($f, true);
        try {
            app(ApReconciliation::class)->decide($id, 1, 'approve', 'Foreign source must fail');
            $this->fail('Foreign source accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->unchangedCreditState($f, $before, 'foreign-binding:approval');
        DB::table('supplier_credit_bindings')->where('id', $binding['id'])->update(['company_id' => $f['company']->id]);
        $foreign->delete();
        // A corrupt foreign company claim must also fail even in the supported sole-company environment.
        DB::table('supplier_credit_bindings')->where('id', $binding['id'])->update(['party_id' => $f['otherParty']->id]);
        $this->rejectedApproval($f, $id, 'supplier_credit_binding_invalid');
    }

    public function test_credit_source_rechecks_grn_chain_at_posting_and_after_proposal(): void
    {
        $f = $this->creditFixture();
        $cases = ['po' => ['purchase_order_id' => $f['otherPo']], 'party' => ['party_id' => $f['otherParty']->id],
            'journal' => ['journal_id' => null], 'bill' => ['purchase_bill_id' => null], 'status' => ['status' => 'VOID'], 'missing' => null];
        foreach ($cases as $name => $change) {
            foreach ([false, true] as $approval) {
                DB::beginTransaction();
                try {
                    $this->creditActor($f);
                    if ($approval) {
                        [, $id] = $this->creditProposal($f);
                    }
                    $receipt = DB::table('purchase_receipts')->where('id', $f['receipt']);
                    $change === null ? $receipt->delete() : $receipt->update($change);
                    if ($approval) {
                        $this->rejectedApproval($f, $id, 'GRN supplier or invoice provenance is inconsistent.');
                    } else {
                        $before = $this->creditSnapshot($f);
                        $this->postJson($f['url'], $f['payload'])->assertConflict();
                        $this->unchangedCreditState($f, $before, 'grn:'.$name.':posting');
                    }
                } finally {
                    DB::rollBack();
                }
            }
        }
    }

    public function test_credit_source_rechecks_seal_and_ap_line_binding_at_retry_and_approval(): void
    {
        $f = $this->creditFixture();
        $cases = ['seal-source', 'seal-key', 'seal-type', 'seal-missing', 'binding-journal', 'binding-ap-line', 'header-bill', 'line-account', 'payload-hash'];
        foreach ($cases as $name) {
            DB::beginTransaction();
            try {
                [$b, $id] = $this->creditProposal($f);
                $binding = DB::table('supplier_credit_bindings')->where('id', $b['id']);
                $intent = DB::table('accounting_posting_intents')->where('journal_id', $b['journal_id']);
                if ($name === 'seal-source') {
                    $intent->update(['source_id' => 'foreign-sealed-source']);
                }
                if ($name === 'seal-key') {
                    $intent->update(['idempotency_key' => 'foreign-sealed-key']);
                }
                if ($name === 'seal-type') {
                    $intent->update(['source_type' => 'GENERIC_JOURNAL']);
                }
                if ($name === 'seal-missing') {
                    $intent->update(['sealed_at' => null]);
                }
                if ($name === 'payload-hash') {
                    $binding->update(['payload_hash' => str_repeat('0', 64)]);
                }
                if ($name === 'binding-journal') {
                    $binding->update(['journal_id' => $f['journal']->getKey()]);
                }
                if ($name === 'binding-ap-line') {
                    $binding->update(['ap_line_id' => $f['journal']->details()->where('position', 'DEBET')->value('id')]);
                }
                // Deliberately corrupt persisted evidence via raw fixture writes, never through the posting use case.
                if ($name === 'header-bill') {
                    DB::table('journal_headers')->where('journal_id', $b['journal_id'])->update(['bill_id' => null]);
                }
                if ($name === 'line-account') {
                    DB::table('journal_details')->where('id', $b['ap_line_id'])->update(['account_code' => '610001']);
                }
                $this->creditActor($f);
                $before = $this->creditSnapshot($f);
                $this->postJson($f['url'], $f['payload'])->assertConflict();
                $this->unchangedCreditState($f, $before, 'binding:'.$name.':retry');
                $this->rejectedApproval($f, $id, $name === 'binding-journal' ? 'unattributed_idr_journal_required' : 'supplier_credit_binding_invalid');
            } finally {
                DB::rollBack();
            }
        }
    }

    public function test_credit_source_rechecks_payment_evidence_at_posting_and_after_proposal(): void
    {
        $f = $this->creditFixture();
        foreach (['event', 'consumed-allocation'] as $evidence) {
            foreach ([false, true] as $approval) {
                DB::beginTransaction();
                try {
                    $this->creditActor($f);
                    if ($approval) {
                        [, $id] = $this->creditProposal($f);
                    }
                    $request = (string) Str::uuid();
                    DB::table('ppl_requests')->insert(['id' => $request, 'company_id' => $f['company']->id, 'owner_user_id' => $f['finance']->id,
                        'doc' => 'PAYMENT-EVIDENCE', 'status' => 'paid', 'payload' => json_encode(['purchaseBillId' => $f['bill']])]);
                    if ($evidence === 'event') {
                        DB::table('ppl_payment_events')->insert(['id' => (string) Str::uuid(), 'request_id' => $request,
                            'company_id' => $f['company']->id, 'confirmed_by' => $f['approver']->id, 'snapshot' => '{}', 'created_at' => now()]);
                    }
                    if ($evidence === 'consumed-allocation') {
                        DB::table('ppl_ap_allocations')->insert(['request_id' => $request,
                            'company_id' => $f['company']->id, 'purchase_bill_id' => $f['bill'], 'amount' => '10.00', 'status' => 'consumed',
                            'source_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
                    }
                    if ($approval) {
                        $this->rejectedApproval($f, $id, 'Invoice has reservation or payment evidence.');
                    } else {
                        $before = $this->creditSnapshot($f);
                        $this->postJson($f['url'], $f['payload'])->assertConflict();
                        $this->unchangedCreditState($f, $before, 'payment:'.$evidence.':posting');
                    }
                } finally {
                    DB::rollBack();
                }
            }
        }
    }
}

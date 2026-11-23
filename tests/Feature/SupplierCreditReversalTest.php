<?php

namespace Tests\Feature;

use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SupplierCreditSourceCases;

class SupplierCreditReversalTest extends \Tests\TestCase
{
    use RefreshDatabase;
    use SupplierCreditSourceCases {
        creditActor as protected;
        creditSnapshot as protected;
    }

    protected function reversalFixture(bool $approved = false): array
    {
        $f = $this->creditFixture();
        Role::where('code', 'CREDIT_SOURCE_TEST')->firstOrFail()->permissions()->attach(Permission::where('code', 'supplier_credit.reverse')->value('id'));
        [$binding, $proposal] = $this->creditProposal($f);
        if ($approved) {
            $this->creditActor($f, true);
            app(ApReconciliation::class)->decide($proposal, 1, 'approve', 'Independent credit approval');
        }
        $this->creditActor($f);
        $url = route('purchase-bills.supplier-credit.reverse', [$f['bill'], $binding['id']]);
        $payload = ['posting_date' => '2026-02-02', 'reason' => 'Supplier withdrew the price allowance'];
        return $f + compact('binding', 'proposal', 'url', 'payload') + ['reverse_url' => $url, 'reverse_payload' => $payload];
    }

    protected function reversalSnapshot(array $f): array
    {
        return $this->creditSnapshot($f) + ['reversals' => DB::table('supplier_credit_reversals')->get()->toArray()];
    }

    public function test_pending_and_approved_credit_reversal_restore_capacity_once(): void
    {
        foreach ([false, true] as $approved) {
            DB::beginTransaction();
            try {
                $f = $this->reversalFixture($approved);
                $this->assertSame($approved ? '180.50' : '200.50', $this->creditSnapshot($f)['projection']['residual']);
                $original = DB::table('journal_details')->where('journal_id', $f['binding']['journal_id'])->get()->toArray();
                $response = $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk()->json();
                $this->assertSame($approved ? '20.00' : '0.00', bcadd((string) $response['correction_amount'], '0', 2));
                $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);
                $this->assertSame([], $this->creditSnapshot($f)['projection']['exceptions']);
                $this->assertEquals($original, DB::table('journal_details')->where('journal_id', $f['binding']['journal_id'])->get()->toArray());
                $this->assertDatabaseCount('journal_headers', 3);
                $this->assertDatabaseCount('supplier_credit_bindings', 1);
                $this->assertDatabaseCount('supplier_credit_reversals', 1);
                $this->assertDatabaseCount('inventory_ledgers', 0);
                $state = $this->reversalSnapshot($f);
                $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk()->assertJsonPath('journal_id', $response['journal_id']);
                $this->postJson($f['reverse_url'], [...$f['reverse_payload'], 'reason' => 'Changed reversal reason'])->assertConflict();
                $this->assertEquals($state, $this->reversalSnapshot($f));
                $this->creditActor($f, true);
                try {
                    app(ApReconciliation::class)->decide($f['proposal'], 1, 'approve', 'Independent credit approval');
                    $this->fail('Reversed credit approved');
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->assertSame(['supplier_credit_reversed'], $e->errors()['reconciliation']);
                }
                $this->assertEquals($state, $this->reversalSnapshot($f));
            } finally { DB::rollBack(); }
        }
    }

    public function test_reversal_without_proposal_stays_pending_and_cannot_be_proposed(): void
    {
        $f = $this->reversalFixture();
        DB::table('ppl_ap_reconciliation_decisions')->delete();
        DB::table('ppl_ap_reconciliations')->delete();
        $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk()->assertJsonPath('reconciliation_id', null);
        $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);
        $this->assertSame([], $this->creditSnapshot($f)['projection']['exceptions']);
        $this->creditActor($f);
        try {
            app(ApReconciliation::class)->propose(['purchase_bill_id' => $f['bill'], 'journal_id' => $f['binding']['journal_id'],
                'amount' => '20.00', 'reason' => 'Attempt after reversal', 'evidence' => 'CN-SOURCE']);
            $this->fail('Reversed credit proposed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(['supplier_credit_binding_invalid'], $e->errors()['reconciliation']);
        }
    }

    public function test_reversal_guards_reject_without_partial_effects(): void
    {
        foreach (['period', 'permission', 'role', 'ownership', 'seal', 'paid', 'hold', 'event', 'legacy', 'legacy-allocation'] as $case) {
            DB::beginTransaction();
            try {
                $f = $this->reversalFixture(true);
                if ($case === 'period') DB::table('accounting_periods')->insert(['month' => '2026-02', 'closed' => true]);
                if ($case === 'permission') Role::where('code', 'CREDIT_SOURCE_TEST')->first()->permissions()->detach(Permission::where('code', 'supplier_credit.reverse')->value('id'));
                if ($case === 'role') $f['finance']->update(['role' => 'STAFF']);
                if ($case === 'ownership') DB::table('supplier_credit_bindings')->where('id', $f['binding']['id'])->update(['party_id' => $f['otherParty']->id]);
                if ($case === 'seal') DB::table('accounting_posting_intents')->where('journal_id', $f['binding']['journal_id'])->update(['source_id' => 'unrelated']);
                if ($case === 'paid') DB::table('purchase_bills')->where('id', $f['bill'])->update(['payment_status' => 'PAID']);
                if (in_array($case, ['hold', 'event'], true)) {
                    $request = (string) \Illuminate\Support\Str::uuid();
                    DB::table('ppl_requests')->insert(['id' => $request, 'company_id' => $f['company']->id, 'owner_user_id' => $f['finance']->id,
                        'doc' => 'REVERSAL-HOLD', 'status' => 'paid', 'payload' => json_encode(['purchaseBillId' => $f['bill']])]);
                    if ($case === 'hold') DB::table('ppl_ap_allocations')->insert(['request_id' => $request, 'company_id' => $f['company']->id,
                        'purchase_bill_id' => $f['bill'], 'amount' => '10.00', 'status' => 'active', 'source_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
                    else DB::table('ppl_payment_events')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'request_id' => $request,
                        'company_id' => $f['company']->id, 'confirmed_by' => $f['approver']->id, 'snapshot' => '{}', 'created_at' => now()]);
                }
                if (in_array($case, ['legacy', 'legacy-allocation'], true)) {
                    $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'REV', 'nama_divisi' => 'Synthetic']);
                    $payment = DB::table('transaksi_payment_plan')->insertGetId(['no_transaksi' => 'VOID-PAYMENT', 'id_divisi' => $division,
                        'tgl_pengajuan' => '2026-01-03', 'jenis_transaksi' => 'BANK', 'penerima_pj' => 'Synthetic', 'keterangan' => 'Payment evidence', 'nominal' => '10.00',
                        'ref_bill_number' => 'SOURCE-BILL', 'status_payment' => 'APPROVED']);
                    if ($case === 'legacy-allocation') DB::table('bill_payment_allocations')->insert(['id_payment' => $payment,
                        'purchase_bill_id' => $f['bill'], 'journal_id' => $f['journal']->getKey(), 'amount' => '10.00']);
                }
                $before = $this->reversalSnapshot($f);
                $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertStatus(in_array($case, ['permission', 'role'], true) ? 403 : 409);
                $this->assertEquals($before, $this->reversalSnapshot($f), $case);
            } finally { DB::rollBack(); }
        }
    }

    public function test_failure_after_journal_and_reversal_insert_rolls_back_everything(): void
    {
        $f = $this->reversalFixture(true);
        $before = $this->reversalSnapshot($f);
        $inject = true;
        $reached = false;
        DB::listen(function ($query) use (&$inject, &$reached) {
            if ($inject && str_contains($query->sql, 'insert into') && str_contains($query->sql, 'ppl_ap_reconciliation_decisions')) {
                $reached = true;
                throw new \RuntimeException('Injected failure after reversal and audit writes');
            }
        });
        $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertStatus(500);
        $inject = false;
        $this->assertTrue($reached, 'Failure must occur after reversal and audit writes, before commit');
        $this->assertEquals($before, $this->reversalSnapshot($f));
        $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk();
        $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);
        $this->assertDatabaseCount('journal_headers', 3);
    }

    public function test_reversal_integrity_and_evidence_retaining_migration(): void
    {
        $f = $this->reversalFixture(true);
        // Original month may close; reversal uses its explicitly open destination month.
        DB::table('accounting_periods')->insert(['month' => '2026-01', 'closed' => true]);
        $reversal = $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk()->json();
        $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);
        $this->assertDatabaseHas('ppl_ap_reconciliations', ['id' => $f['proposal'], 'status' => 'approved', 'approved_by' => $f['approver']->id]);
        $this->assertDatabaseHas('ppl_ap_reconciliation_decisions', ['reconciliation_id' => $f['proposal'], 'action' => 'credit_reversed', 'actor_id' => $f['finance']->id]);
        $migration = require database_path('migrations/2026_11_19_000001_create_supplier_credit_reversals.php');
        try { $migration->down(); $this->fail('Used reversal evidence removed'); }
        catch (\RuntimeException $e) { $this->assertSame('Supplier credit reversal evidence must be retained.', $e->getMessage()); }
        DB::table('accounting_posting_intents')->where('journal_id', $reversal['journal_id'])->update(['source_id' => 'wrong']);
        $before = $this->reversalSnapshot($f);
        $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertConflict();
        $this->assertEquals($before, $this->reversalSnapshot($f));
        $this->assertSame('supplier_credit_reversal_invalid', $before['projection']['exceptions'][0]['reason']);
    }
}
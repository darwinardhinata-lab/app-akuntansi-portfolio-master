<?php

namespace Tests\Feature;

use App\Services\GrnReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\GrnScenarios;
use Tests\TestCase;

final class GrnBillOwnershipTest extends TestCase
{
    use RefreshDatabase, GrnScenarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGrnScenario();
    }

    public function test_new_bill_persists_locked_po_and_retry_preserves_links(): void
    {
        $key = (string) Str::uuid();
        $id = $this->receive(40, 'BIL-OWNERSHIP', $key);
        $receipt = DB::table('purchase_receipts')->find($id);
        $bill = DB::table('purchase_bills')->find($receipt->purchase_bill_id);
        $this->assertEquals($this->po->id, $bill->purchase_order_id);
        $this->assertEquals($this->company->id, $receipt->company_id);
        $this->assertSame($bill->journal_id, $receipt->journal_id);
        $this->assertSame($id, $this->receive(40, 'BIL-OWNERSHIP', $key));
        $this->assertSame(1, DB::table('journal_headers')->count());
    }

    public function test_retry_rejects_corrupt_or_ambiguous_links_without_repairing_history(): void
    {
        $key = (string) Str::uuid();
        $id = $this->receive(40, 'BIL-OWNERSHIP', $key);
        $receipt = DB::table('purchase_receipts')->find($id);
        foreach (['null-po', 'missing-bill-journal', 'duplicate-bill', 'receipt-po', 'journal-source'] as $case) {
            DB::beginTransaction();
            try {
                if ($case === 'null-po') DB::table('purchase_bills')->where('id', $receipt->purchase_bill_id)->update(['purchase_order_id' => null]);
                if ($case === 'missing-bill-journal') DB::table('purchase_bills')->where('id', $receipt->purchase_bill_id)->update(['journal_id' => null]);
                if ($case === 'duplicate-bill') DB::table('purchase_bills')->insert(['bill_number' => 'NEGATIVE-DUPLICATE', 'bill_date' => '2026-09-25', 'vendor_name' => 'Negative fixture', 'journal_id' => $receipt->journal_id]);
                if ($case === 'receipt-po') DB::table('purchase_receipts')->where('id', $id)->update(['purchase_order_id' => null]);
                if ($case === 'journal-source') DB::table('journal_headers')->where('journal_id', $receipt->journal_id)->update(['source_doc_no' => 'CONFLICT']);
                $before = $this->snapshot();
                $caught = null;
                try { $this->receive(40, 'BIL-OWNERSHIP', $key); } catch (\RuntimeException $e) { $caught = $e; }
                $this->assertNotNull($caught, $case);
                $this->assertSame($before, $this->snapshot(), $case);
            } finally { DB::rollBack(); }
        }
    }

    public function test_inconsistent_legacy_callback_rolls_back_new_bill_and_receipt(): void
    {
        $caught = null;
        try {
            app(GrnReceivingService::class)->receive($this->po->id, '2026-09-25', [$this->detailId => 40], 'BIL-BAD-CALLBACK', null, (string) Str::uuid(), function () {
                DB::table('purchase_bills')->where('bill_number', 'BIL-BAD-CALLBACK')->update(['purchase_order_id' => null]);
                $journal = \App\Support\PostingService::post(['transaction_date' => '2026-09-25', 'source_doc_no' => 'BIL-BAD-CALLBACK'], [
                    ['account_code' => '114001', 'position' => 'DEBET', 'amount' => 400],
                    ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => 400],
                ]);
                DB::table('purchase_bills')->where('bill_number', 'BIL-BAD-CALLBACK')->update(['journal_id' => $journal->getKey()]);
            });
        } catch (\RuntimeException $e) { $caught = $e; }
        $this->assertNotNull($caught);
        $this->assertEmptyReceiving();
    }

    private function snapshot(): array
    {
        return collect(['purchase_orders', 'purchase_bills', 'purchase_receipts', 'journal_headers', 'journal_details', 'products', 'inventory_ledgers'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    }

    public function test_actual_posting_links_gl_to_authorized_bill_and_revocation_hides_source(): void
    {
        $id = $this->receive(40, 'BIL-GL-ACTUAL');
        $receipt = DB::table('purchase_receipts')->find($id);
        $role = $this->prepareGlReader();
        $url = \App\Filament\Pages\GeneralLedger::getUrl(['start' => '2026-09-01', 'end' => '2026-09-30',
            'account' => '114001', 'journal' => $receipt->journal_id]);
        $before = $this->snapshot();
        $this->get($url)->assertOk()->assertSee('data-gl-source', false)
            ->assertSee(route('purchase-bills.show', $receipt->purchase_bill_id), false);
        $this->get(route('purchase-bills.show', $receipt->purchase_bill_id))->assertOk()->assertSee('PO-GRN');
        $role->permissions()->detach(\App\Modules\Platform\Models\Permission::where('code', 'purchase_bill.view')->value('id'));
        $this->get($url)->assertOk()->assertSee(__('gl.unlinked'))->assertDontSee('data-gl-source', false);
        $this->get(route('purchase-bills.show', $receipt->purchase_bill_id))->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_gl_rejects_ambiguous_or_inconsistent_actual_posting_relations(): void
    {
        $id = $this->receive(40, 'BIL-GL-ACTUAL');
        $receipt = DB::table('purchase_receipts')->find($id);
        $this->prepareGlReader();
        $url = \App\Filament\Pages\GeneralLedger::getUrl(['start' => '2026-09-01', 'end' => '2026-09-30',
            'account' => '114001', 'journal' => $receipt->journal_id]);
        foreach (['null-po', 'duplicate-bill', 'receipt-po', 'journal-source', 'draft', 'unowned-po', 'missing-receipt-journal', 'duplicate-receipt'] as $case) {
            DB::beginTransaction();
            try {
                if ($case === 'null-po') DB::table('purchase_bills')->where('id', $receipt->purchase_bill_id)->update(['purchase_order_id' => null]);
                if ($case === 'duplicate-bill') DB::table('purchase_bills')->insert(['bill_number' => 'NEGATIVE-GL', 'bill_date' => '2026-09-25', 'vendor_name' => 'Negative', 'journal_id' => $receipt->journal_id]);
                if ($case === 'receipt-po') DB::table('purchase_receipts')->where('id', $id)->update(['purchase_order_id' => null]);
                if ($case === 'journal-source') DB::table('journal_headers')->where('journal_id', $receipt->journal_id)->update(['source_doc_no' => 'CONFLICT']);
                if ($case === 'draft') DB::table('purchase_receipts')->where('id', $id)->update(['status' => 'DRAFT']);
                if ($case === 'unowned-po') DB::table('purchase_orders')->where('id', $this->po->id)->update(['company_id' => null]);
                if ($case === 'missing-receipt-journal') DB::table('purchase_receipts')->where('id', $id)->update(['journal_id' => null]);
                if ($case === 'duplicate-receipt') {
                    $row = (array) $receipt; unset($row['id']);
                    $row['receipt_number'] = 'NEGATIVE-GL'; $row['idempotency_key'] = (string) Str::uuid(); $row['purchase_bill_id'] = null;
                    DB::table('purchase_receipts')->insert($row);
                }
                $before = $this->snapshot();
                $this->get($url)->assertOk()->assertSee(__('gl.unlinked'))->assertDontSee('data-gl-source', false);
                $this->assertSame($before, $this->snapshot(), $case);
            } finally { DB::rollBack(); }
        }
    }

    private function prepareGlReader(): \App\Modules\Platform\Models\Role
    {
        config(['platform.filament_enabled' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = \App\Modules\Platform\Models\Role::create(['code' => 'GRN_GL_READER', 'name' => 'Synthetic reader']);
        $role->permissions()->attach(\App\Modules\Platform\Models\Permission::whereIn('code', ['erp.panel.access', 'gl.view', 'purchase_bill.view'])->pluck('id'));
        $this->member->platformRoles()->attach($role->id, ['company_id' => $this->company->id]);
        $this->withSession([\App\Modules\Platform\Support\CompanyContext::SESSION_KEY => $this->company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (! $request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('erp'));
        return $role;
    }
}
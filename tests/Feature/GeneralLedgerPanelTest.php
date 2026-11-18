<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralLedger;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\GeneralLedgerRead;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class GeneralLedgerPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private Role $role;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.filament_enabled' => true, 'libretranslate.enabled' => false]);
        $this->company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $this->reader = User::factory()->create();
        $this->reader->companies()->attach($this->company->id, ['is_default' => true]);
        $this->seed(PlatformPermissionSeeder::class);
        $this->role = Role::create(['code' => 'GL_READER', 'name' => 'GL reader']);
        $this->role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'gl.view'])->pluck('id'));
        $this->reader->platformRoles()->attach($this->role->id, ['company_id' => $this->company->id]);
        $this->actingAs($this->reader)->withSession([CompanyContext::SESSION_KEY => $this->company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (!$request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        Filament::setCurrentPanel(Filament::getPanel('erp'));
        foreach (['111001' => 'DEBET', '211001' => 'KREDIT', '44001' => 'DEBET'] as $code => $normal) {
            Account::create(['account_code' => (string) $code, 'account_name' => 'Account '.$code,
                'normal_balance' => $normal, 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
        }
    }

    private function entry(string $date, string $amount, string $debit = '111001', string $credit = '211001'): JournalHeader
    {
        $header = JournalHeader::create(['transaction_date' => $date, 'journal_type' => 'MANUAL', 'source_doc_no' => 'INV-FAKE-REFERENCE']);
        foreach ([[$debit, 'DEBET'], [$credit, 'KREDIT']] as [$code, $side]) {
            JournalDetail::create(['journal_id' => $header->journal_id, 'account_code' => $code, 'position' => $side, 'amount' => $amount]);
        }
        return $header;
    }

    public function test_summary_full_period_and_paginated_trace_reconcile_without_writes(): void
    {
        $this->entry('2026-09-30 23:59:59', '100.25');
        for ($i = 0; $i < 26; $i++) $last = $this->entry('2026-10-31 23:59:59', '0.10');
        $this->entry('2026-11-01 00:00:00', '999.99');
        $contra = $this->entry('2026-10-01', '1.25', '44001');
        $before = [DB::table('journal_headers')->get()->toJson(), DB::table('journal_details')->get()->toJson()];
        $params = ['start' => '2026-10-01', 'end' => '2026-10-31'];
        $this->get(GeneralLedger::getUrl($params))->assertOk()->assertSee('102.85');
        $data = app(GeneralLedgerRead::class)->read($params['start'], $params['end'], '111001', '', 2);
        $this->assertSame('100.25', $data['summary']['111001']['opening']);
        $this->assertSame('2.60', $data['summary']['111001']['debit']);
        $this->assertSame('102.85', $data['summary']['111001']['closing']);
        $this->assertSame('104.10', $data['summary']['211001']['closing']);
        $this->assertSame('1.25', $data['summary']['44001']['closing']);
        $this->assertSame('102.75', $data['pageOpening']);
        $this->assertSame('102.85', $data['transactions']->first()->running_balance);
        $this->get(GeneralLedger::getUrl($params + ['account' => '111001', 'journal' => $last->journal_id]))
            ->assertOk()->assertSee('INV-FAKE-REFERENCE')->assertSee(__('gl.unlinked'))->assertDontSee('data-gl-source', false)
            ->assertDontSee(route('jurnal.edit', $last->journal_id), false);
        $this->get(GeneralLedger::getUrl($params + ['account' => '111001', 'journal' => $contra->journal_id]))->assertNotFound();
        $this->get(GeneralLedger::getUrl($params + ['account' => 'UNKNOWN']))->assertNotFound();
        $this->assertSame($before, [DB::table('journal_headers')->get()->toJson(), DB::table('journal_details')->get()->toJson()]);
    }

    public function test_permission_membership_and_second_company_revoke_old_page_access(): void
    {
        $page = Livewire::test(GeneralLedger::class)->assertOk();
        $permission = Permission::where('code', 'gl.view')->sole();
        $this->role->permissions()->detach($permission->id);
        $this->get(GeneralLedger::getUrl())->assertForbidden();
        $page->call('$refresh')->assertForbidden();
        $this->role->permissions()->attach($permission->id);
        $page = Livewire::test(GeneralLedger::class)->assertOk();
        $this->reader->companies()->detach($this->company->id);
        $page->call('$refresh')->assertForbidden();
        $this->reader->companies()->attach($this->company->id, ['is_default' => true]);
        $page = Livewire::test(GeneralLedger::class)->assertOk();
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'active' => true, 'base_currency' => 'IDR']);
        $page->call('$refresh')->assertForbidden();
        $this->get(GeneralLedger::getUrl())->assertForbidden();
    }

    public function test_explicit_payment_source_requires_existing_actor_policy_and_rechecks_at_destination(): void
    {
        config(['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $journal = $this->entry('2026-10-01', '100.00');
        $id = (string) \Illuminate\Support\Str::uuid();
        $event = (string) \Illuminate\Support\Str::uuid();
        $payload = ['id' => $id, 'doc' => 'PPL-EXPLICIT-001', 'createdBy' => 'different-owner', 'department' => 'OTHER'];
        DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $this->company->id, 'owner_user_id' => $this->reader->id,
            'doc' => $payload['doc'], 'status' => 'posted', 'version' => 1, 'payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ppl_payment_events')->insert(['id' => $event, 'request_id' => $id, 'company_id' => $this->company->id,
            'confirmed_by' => $this->reader->id, 'snapshot' => '{}', 'journal_id' => $journal->journal_id, 'created_at' => now()]);
        $params = ['start' => '2026-10-01', 'end' => '2026-10-31', 'account' => '111001', 'journal' => $journal->journal_id];
        $url = GeneralLedger::getUrl($params);
        $this->get($url)->assertOk()->assertDontSee('data-gl-source', false);
        $requester = Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        $this->reader->platformRoles()->attach($requester->id, ['company_id' => $this->company->id]);
        $this->get($url)->assertOk()->assertDontSee('data-gl-source', false);
        $this->get(route('payment-plan-v33.print', $id))->assertForbidden();
        $finance = Role::create(['code' => 'PPL_ACCOUNTANT', 'name' => 'Accountant']);
        $this->reader->platformRoles()->attach($finance->id, ['company_id' => $this->company->id]);
        $this->get($url)->assertOk()->assertSee('data-gl-source', false)->assertSee(route('payment-plan-v33.print', $id));
        // Source destination has its own authorization after a link has been rendered.
        $this->reader->platformRoles()->detach($finance->id);
        $this->get(route('payment-plan-v33.print', $id))->assertForbidden();
        $this->get($url)->assertOk()->assertDontSee('data-gl-source', false);
    }

    public function test_read_filters_validate_and_foreign_snapshot_is_not_used_as_base_amount(): void
    {
        $header = $this->entry('2026-12-31', '15000.15');
        // Import-like legacy snapshot, not a new FX posting writer.
        DB::table('journal_details')->where('journal_id', $header->journal_id)->update([
            'currency_code' => 'USD', 'amount_txn' => '1.000010', 'exchange_rate' => '15000.000000']);
        $page = Livewire::test(GeneralLedger::class, ['start' => '2026-12-01', 'end' => '2026-12-31',
            'account' => '111001', 'journal' => $header->journal_id]);
        $page->assertOk()->assertSee('15000.15')->assertSee('USD')->assertSee('1.0000');
        $page->set('end', '2026-11-30')->call('apply')->assertHasErrors(['end']);
        $page->set('end', '2026-12-31')->set('start', 'not-a-date')->call('apply')->assertHasErrors(['start']);
        $page->set('start', '2026-12-01')->set('page', 0)->call('apply')->assertHasErrors(['page']);
    }
}
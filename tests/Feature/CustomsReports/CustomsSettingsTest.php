<?php

namespace Tests\Feature\CustomsReports;

use App\Models\User;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.order_company_scope_enabled' => false, 'customs.enabled' => false]);
        Http::preventStrayRequests();
    }

    public function test_admin_can_view_and_save_internal_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->get(route('customs-settings.edit'))->assertOk()->assertSee('h2h_enabled', false)->assertSee('disabled', false);
        $this->put(route('customs-settings.update'), ['h2h_enabled' => 0, 'auto_sync_internal' => 0])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('cbr_settings', ['id' => 1, 'auto_sync_internal' => 0]);
        $this->assertDatabaseHas('system_logs', ['module' => 'Pengaturan Bea Cukai']);
        $this->put(route('customs-settings.update'), ['h2h_enabled' => 0, 'auto_sync_internal' => 1])->assertRedirect();
        $this->assertDatabaseCount('cbr_settings', 1);
        $this->assertDatabaseHas('cbr_settings', ['auto_sync_internal' => 1]);
        Http::assertNothingSent();
    }

    public function test_h2h_activation_is_rejected_even_with_a_forged_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->put(route('customs-settings.update'), ['h2h_enabled' => 1, 'auto_sync_internal' => 1])
            ->assertSessionHasErrors('h2h_enabled');
        $this->assertDatabaseCount('cbr_settings', 0);
        $this->assertFalse(config('customs.enabled'));
        Http::assertNothingSent();
    }

    public function test_settings_require_login_and_admin_role(): void
    {
        $this->get(route('customs-settings.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'STAFF']));
        $this->get(route('customs-settings.edit'))->assertForbidden();
        $this->put(route('customs-settings.update'), ['h2h_enabled' => 0, 'auto_sync_internal' => 1])->assertForbidden();
    }

    public function test_internal_draft_auto_populates_and_resync_is_idempotent(): void
    {
        DB::table('mfg_yarns')->insert(['yarn_code' => 'YR-AUTO', 'yarn_type' => 'Cotton', 'unit' => 'KGS', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_material_ledgers')->insert([
            'transaction_date' => '2026-09-01', 'evidence_number' => 'ML-AUTO', 'item_type' => 'YARN', 'item_id' => 1,
            'type' => 'IN', 'qty' => 12, 'unit_cost' => 0, 'total_cost' => 0, 'running_qty' => 12,
            'running_value' => 0, 'moving_average_cost' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(ReportPeriodService::class);
        $period = $service->createDraft(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, 9, 2026);
        $this->assertDatabaseHas('cbr_mutasi_lines', ['report_period_id' => $period->id, 'kode_barang' => 'YR-AUTO', 'jumlah_pemasukan_barang' => 12]);
        $service->populateMutasiBahanBaku($period);
        $this->assertDatabaseCount('cbr_mutasi_lines', 1);
        $this->assertDatabaseCount('mfg_material_ledgers', 1);
        Http::assertNothingSent();
    }

    public function test_disabling_auto_sync_keeps_new_drafts_empty(): void
    {
        DB::table('cbr_settings')->insert(['id' => 1, 'auto_sync_internal' => false]);
        $period = app(ReportPeriodService::class)->createDraft(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, 9, 2026);
        $this->assertSame(0, $period->lines()->count());
    }

    public function test_non_h2h_mode_blocks_h2h_population_without_changing_reports(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $period = app(ReportPeriodService::class)->createDraft(ReportPeriod::TYPE_PEMASUKAN, 9, 2026);
        $this->post(route('customs-reports.populate-from-h2h', $period))->assertRedirect()->assertSessionHas('error');
        $this->get(route('customs-reports.show', $period))->assertOk()->assertDontSee('Populate dari H2H');
        $this->assertSame(0, $period->lines()->count());
        Http::assertNothingSent();
    }
}
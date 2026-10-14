<?php

namespace Tests\Feature\Currency;

use App\Models\User;
use App\Modules\Currency\Models\ExchangeRate;
use App\Modules\Currency\Services\ExchangeRateService;
use App\Modules\Platform\Models\Company;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManualFxJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->actingAs(User::factory()->create());
        config(['platform.multicurrency_enabled' => true]);
        if (! Route::has('currency.rates.lookup')) {
            require base_path('routes/currency.php');
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        }
        $this->seed(CurrencySeeder::class);
        Company::query()->firstOrCreate(['code' => 'MGI'], ['name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Bank USD', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA'],
            ['account_code' => '31100', 'account_name' => 'Modal', 'coa_type' => 'EQUITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA'],
        ]);
        app(ExchangeRateService::class)->save(['currency_code' => 'USD', 'rate' => '15800',
            'rate_date' => '2026-10-09', 'type' => 'SPOT', 'source' => 'BI'], null, auth()->id());
    }

    private function payload(): array
    {
        return ['transaction_date' => '2026-10-09', 'description' => 'Modal USD', 'currency_code' => 'USD',
            'details' => [
                ['account_code' => '11100', 'position' => 'DEBET', 'amount_txn' => '1.000,00'],
                ['account_code' => '31100', 'position' => 'KREDIT', 'amount_txn' => '1.000,00'],
            ]];
    }

    public function test_base_currency_uses_operational_company_and_respects_explicit_company(): void
    {
        $service = \App\Modules\Currency\Services\FxPostingService::class;
        $this->assertSame('IDR', $service::baseCurrency());
        $company = Company::query()->where('code', 'MGI')->firstOrFail();
        $company->update(['base_currency' => 'CNY']);
        $this->assertSame('CNY', $service::baseCurrency());
        $this->assertSame('CNY', $service::baseCurrency((int) $company->id));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mata uang FX sama dengan mata uang dasar perusahaan.');
        $service::prepareManualJournal([], 'CNY', '1', \Carbon\Carbon::parse('2026-10-09'));
    }

    public function test_opening_decimal_totals_are_exact_and_one_cent_difference_is_rejected(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        DB::table('accounts')->insert(['account_code' => '11200', 'account_name' => 'Kas',
            'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        $payload = ['transaction_date' => '2026-10-09',
            'balances' => ['11100' => '0.1', '11200' => '0.2', '31100' => '0.30'],
            'exchange_rates' => ['USD' => '1'], 'rate_reasons' => ['USD' => 'Uji desimal eksak']];
        $this->post(route('account.store_opening_balance'), $payload)->assertSessionMissing('error');
        $this->assertDatabaseHas('journal_details', ['account_code' => '11100', 'amount' => '0.10']);
        $this->assertDatabaseHas('journal_details', ['account_code' => '11200', 'amount' => '0.20']);
        $this->assertDatabaseHas('journal_details', ['account_code' => '31100', 'amount' => '0.30']);
        $before = DB::table('journal_details')->orderBy('id')->get()->toJson();
        $payload['balances']['31100'] = '0.31';
        $this->post(route('account.store_opening_balance'), $payload)
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'tidak balance'));
        $this->assertSame($before, DB::table('journal_details')->orderBy('id')->get()->toJson());
    }

    public function test_customs_and_legacy_sync_do_not_block_manual_or_opening_fx(): void
    {
        foreach (['customs.enabled', 'platform.legacy_sync_enabled'] as $flag) {
            config([$flag => true]);
            $this->post(route('jurnal.store'), $this->payload())->assertRedirect(route('jurnal.index'));
            DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
            $this->post(route('account.store_opening_balance'), [
                'transaction_date' => '2026-10-09', 'balances' => ['11100' => '500', '31100' => '7900000'],
            ])->assertRedirect(route('account.index'))->assertSessionMissing('error');
            $this->assertDatabaseHas('journal_details', ['amount_txn' => '500.0000', 'amount' => '7900000.00']);
        }
        $this->assertDatabaseCount('journal_headers', 3);
    }

    public function test_idr_manual_and_opening_paths_remain_legacy_when_flag_is_enabled(): void
    {
        $this->post(route('jurnal.store'), [
            'transaction_date' => '2026-10-09', 'description' => 'Golden IDR', 'currency_code' => 'IDR',
            'details' => [
                ['account_code' => '11100', 'position' => 'DEBET', 'amount' => '1.250,50'],
                ['account_code' => '31100', 'position' => 'KREDIT', 'amount' => '1.250,50'],
            ],
        ])->assertRedirect(route('jurnal.index'));
        $manual = DB::table('journal_headers')->where('journal_type', 'MANUAL')->first();
        $this->assertSame('IDR', $manual->currency_code);
        $this->assertEquals('1', $manual->exchange_rate);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $manual->journal_id, 'amount' => '1250.50',
            'currency_code' => 'IDR', 'amount_txn' => null, 'exchange_rate' => '1']);

        $this->post(route('account.store_opening_balance'), [
            'transaction_date' => '2026-10-08', 'balances' => ['11100' => '2500.50', '31100' => '2500.50'],
        ])->assertRedirect(route('account.index'));
        $opening = DB::table('journal_headers')->where('journal_type', 'OPENING')->first();
        $this->assertSame('IDR', $opening->currency_code);
        $this->assertEquals('1', $opening->exchange_rate);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $opening->journal_id, 'amount' => '2500.50',
            'currency_code' => 'IDR', 'amount_txn' => null, 'exchange_rate' => '1']);
    }

    // FIX: multicurrency — les formulaires affichent les snapshots sans confondre USD et valeur de base.
    public function test_forms_render_currency_controls_and_existing_opening_transaction_snapshot(): void
    {
        $this->get(route('jurnal.create'))->assertOk()->assertSee('id="fxCurrency"', false)
            ->assertSee('name="rate_reason"', false);
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $this->post(route('account.store_opening_balance'), ['transaction_date' => '2026-10-09',
            'balances' => ['11100' => '0', '31100' => '15800000'], 'amount_txn' => ['11100' => '1000']])
            ->assertSessionMissing('error');
        $this->get(route('account.opening_balance'))->assertOk()
            ->assertSee('name="amount_txn[11100]"', false)
            ->assertSee('value="1000"', false)
            ->assertSee('name="exchange_rates[USD]"', false);
    }

    public function test_http_store_snapshots_master_rate_blocks_update_and_allows_destroy(): void
    {
        $this->post(route('jurnal.store'), $this->payload())->assertRedirect(route('jurnal.index'));
        $journal = DB::table('journal_headers')->first();
        $this->assertSame('USD', $journal->currency_code);
        $this->assertEquals('15800', $journal->exchange_rate);
        $this->assertDatabaseHas('journal_details', ['account_code' => '11100', 'amount' => '15800000',
            'amount_txn' => '1000', 'currency_code' => 'USD', 'exchange_rate' => '15800']);
        $this->assertNotNull(ExchangeRate::query()->firstOrFail()->first_used_at);
        $this->put(route('jurnal.update', $journal->journal_id), $this->payload())
            ->assertSessionHas('error', 'Jurnal valas tidak dapat diedit; hapus lalu buat ulang.');
        $this->assertDatabaseCount('journal_details', 2);
        $this->from(route('jurnal.index'))->delete(route('jurnal.destroy', $journal->journal_id))
            ->assertRedirect(route('jurnal.index'));
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_http_override_requires_reason_and_does_not_mark_master_used(): void
    {
        $payload = array_merge($this->payload(), ['exchange_rate' => '16000']);
        $this->postJson(route('jurnal.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->assertDatabaseCount('journal_headers', 0);
        $this->post(route('jurnal.store'), array_merge($payload, ['rate_reason' => 'Kurs kontrak']))
            ->assertRedirect(route('jurnal.index'));
        $this->assertNull(ExchangeRate::query()->firstOrFail()->first_used_at);
        $this->assertDatabaseHas('journal_details', ['amount' => '16000000', 'exchange_rate' => '16000']);
        $this->assertDatabaseHas('system_logs', [
            'action' => 'UPDATE',
            'module' => 'Jurnal Umum',
            'description' => 'Override kurs USD: master 15800.00000000 → 16000.00000000; alasan: Kurs kontrak',
        ]);
    }

    public function test_disabled_flag_preserves_existing_fx_journal_on_update_without_company_lookup(): void
    {
        $this->post(route('jurnal.store'), $this->payload())->assertSessionMissing('error');
        $journal = DB::table('journal_headers')->first();
        config(['platform.multicurrency_enabled' => false]);
        Company::query()->update(['active' => false]);
        $beforeHeaders = DB::table('journal_headers')->orderBy('journal_id')->get()->toJson();
        $beforeDetails = DB::table('journal_details')->orderBy('id')->get()->toJson();
        $this->put(route('jurnal.update', $journal->journal_id), [])
            ->assertSessionHas('error', 'Jurnal valas tidak dapat diedit; hapus lalu buat ulang.');
        $this->assertSame($beforeHeaders, DB::table('journal_headers')->orderBy('journal_id')->get()->toJson());
        $this->assertSame($beforeDetails, DB::table('journal_details')->orderBy('id')->get()->toJson());
        DB::table('journal_headers')->where('journal_id', $journal->journal_id)->update(['exchange_rate' => 1]);
        $this->put(route('jurnal.update', $journal->journal_id), [])
            ->assertSessionHas('error', 'Jurnal valas tidak dapat diedit; hapus lalu buat ulang.');
        DB::table('journal_details')->update(['amount_txn' => null]);
        DB::table('journal_headers')->where('journal_id', $journal->journal_id)->update(['exchange_rate' => 15800]);
        $this->put(route('jurnal.update', $journal->journal_id), [])
            ->assertSessionHas('error', 'Jurnal valas tidak dapat diedit; hapus lalu buat ulang.');
    }

    public function test_disabled_flag_preserves_fx_opening_on_form_and_csv_replacement(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $this->post(route('account.store_opening_balance'), ['transaction_date' => '2026-10-09',
            'balances' => ['11100' => '1000', '31100' => '15800000']])->assertSessionMissing('error');
        config(['platform.multicurrency_enabled' => false]);
        Company::query()->update(['active' => false]);
        // Omit the foreign account to exercise replacement, not the account-currency guard.
        $beforeHeaders = DB::table('journal_headers')->orderBy('journal_id')->get()->toJson();
        $beforeDetails = DB::table('journal_details')->orderBy('id')->get()->toJson();
        $this->post(route('account.store_opening_balance'), ['transaction_date' => '2026-10-09',
            'balances' => ['31100' => '0']])->assertSessionHas('error');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('opening.csv', "kode,nama,debet,kredit\n31100,Modal,0,0\n");
        $this->post(route('account.import_opening_balance'), ['tanggal_saldo' => '2026-10-09', 'file' => $file])
            ->assertSessionHas('error');
        $this->assertSame($beforeHeaders, DB::table('journal_headers')->orderBy('journal_id')->get()->toJson());
        $this->assertSame($beforeDetails, DB::table('journal_details')->orderBy('id')->get()->toJson());
    }

    public function test_disabled_flag_keeps_legacy_idr_update_without_company_lookup(): void
    {
        config(['platform.multicurrency_enabled' => false]);
        Company::query()->update(['active' => false]);
        $payload = ['transaction_date' => '2026-10-09', 'description' => 'Golden IDR', 'details' => [
            ['account_code' => '11100', 'position' => 'DEBET', 'amount' => '1250.50'],
            ['account_code' => '31100', 'position' => 'KREDIT', 'amount' => '1250.50'],
        ]];
        $this->post(route('jurnal.store'), $payload)->assertSessionMissing('error');
        $journal = DB::table('journal_headers')->first();
        $payload['description'] = 'Golden edited';
        $this->put(route('jurnal.update', $journal->journal_id), $payload)->assertSessionMissing('error');
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $journal->journal_id, 'notes' => 'Golden edited']);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journal->journal_id, 'amount' => '1250.50',
            'currency_code' => 'IDR', 'amount_txn' => null, 'exchange_rate' => 1]);
    }

    public function test_opening_mixed_currency_replaces_details_without_duplicate_header(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $payload = ['transaction_date' => '2026-10-09', 'balances' => ['11100' => '1.000,00', '31100' => '15.800.000,00']];
        $this->post(route('account.store_opening_balance'), $payload)
            ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect(route('account.index'));
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => 'JRN-SA-2026-10-09', 'journal_type' => 'OPENING', 'currency_code' => 'IDR']);
        $this->assertDatabaseHas('journal_details', ['account_code' => '11100', 'amount_txn' => '1000', 'amount' => '15800000', 'currency_code' => 'USD']);
        $this->assertNotNull(ExchangeRate::query()->firstOrFail()->first_used_at);
        $payload['balances'] = ['11100' => '2000', '31100' => '31600000'];
        $this->post(route('account.store_opening_balance'), $payload)->assertSessionMissing('error');
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseHas('journal_details', ['account_code' => '11100', 'amount_txn' => '2000', 'amount' => '31600000']);
    }

    public function test_opening_foreign_account_fails_closed_and_control_account_is_rejected(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $payload = ['transaction_date' => '2026-10-09', 'balances' => ['11100' => '1000', '31100' => '15800000']];
        config(['platform.multicurrency_enabled' => false]);
        $this->post(route('account.store_opening_balance'), $payload)->assertSessionHas('error');
        $this->assertDatabaseCount('journal_headers', 0);
        config(['platform.multicurrency_enabled' => true, 'coa.piutang_usaha' => '11100']);
        $this->post(route('account.store_opening_balance'), $payload)
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'dokumen terbuka'));
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertNull(ExchangeRate::query()->firstOrFail()->first_used_at);
    }

    public function test_opening_csv_foreign_account_rejects_entire_import(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('opening.csv', "kode,nama,debet,kredit\n11100,Bank,1000,0\n31100,Modal,0,1000\n");
        $this->post(route('account.import_opening_balance'), ['tanggal_saldo' => '2026-10-09', 'file' => $file])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'gunakan form saldo awal'));
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_opening_failed_replacement_preserves_previous_snapshots(): void
    {
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $payload = ['transaction_date' => '2026-10-09', 'balances' => ['11100' => '1000', '31100' => '15800000']];
        $this->post(route('account.store_opening_balance'), $payload)->assertSessionMissing('error');
        $before = DB::table('journal_details')->orderBy('id')->get()->toJson();
        $payload['balances']['31100'] = '15800001';
        $this->post(route('account.store_opening_balance'), $payload)
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'tidak balance'));
        $this->assertSame($before, DB::table('journal_details')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('journal_headers', 1);
    }

    public function test_closed_period_rejects_manual_and_opening_fx_without_marking_master(): void
    {
        config(['platform.period_lifecycle_preview_enabled' => true]);
        DB::table('accounting_periods')->insert(['month' => '2026-10', 'closed' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->postJson(route('jurnal.store'), $this->payload())->assertStatus(422);
        DB::table('accounts')->where('account_code', '11100')->update(['currency_code' => 'USD']);
        $this->post(route('account.store_opening_balance'), ['transaction_date' => '2026-10-09',
            'balances' => ['11100' => '1000', '31100' => '15800000']])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'period is closed'));
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
        $this->assertNull(ExchangeRate::query()->firstOrFail()->first_used_at);
    }
}
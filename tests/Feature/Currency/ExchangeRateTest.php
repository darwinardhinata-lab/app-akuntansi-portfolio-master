<?php

// FIX: multicurrency — tes HTTP dan service pada database terisolasi, tanpa kurs jaringan.
namespace Tests\Feature\Currency;

use App\Models\User;
use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Models\ExchangeRate;
use App\Modules\Currency\Services\ExchangeRateNotFound;
use App\Modules\Currency\Services\ExchangeRateService;
use App\Modules\Currency\Services\FxPostingService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\CompanyContext;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.multicurrency_enabled' => true]);
        // FIX: multicurrency — pemanggilan FX membaca perusahaan operasional dari fixture.
        Company::query()->firstOrCreate(['code' => 'MGI'],
            ['name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        if (! Route::has('currency.index')) {
            require base_path('routes/currency.php');
            // FIX: multicurrency — bangun ulang lookup nama route setelah registrasi di lingkungan HTTP uji.
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        }
        $this->seed(CurrencySeeder::class);
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['currency_code' => 'USD', 'rate' => '15800.00000000', 'rate_date' => '2026-10-09', 'type' => 'SPOT', 'source' => 'BI'], $changes);
    }

    public function test_lookup_returns_master_missing_and_requires_login(): void
    {
        $url = route('currency.rates.lookup', ['currency' => 'USD', 'date' => '2026-10-09']);
        $this->getJson($url)->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->getJson($url)->assertOk()->assertExactJson(['rate' => null, 'source' => null, 'found' => false]);
        app(ExchangeRateService::class)->save($this->payload(), null, $user->id);
        $this->getJson($url)->assertOk()->assertExactJson(['rate' => '15800.00000000', 'source' => 'BI', 'found' => true]);
        $this->assertNull(ExchangeRate::query()->firstOrFail()->first_used_at);
    }

    private function postWithMaster(string $id, bool $fail = false): void
    {
        $lines = [
            ['account_code' => '111001', 'position' => 'DEBET', 'amount_txn' => '1.00'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount_txn' => '1.00'],
        ];
        if ($fail) {
            foreach ($lines as &$line) {
                $line['missing_column'] = 'invalid';
            }
            unset($line);
        }
        app(FxPostingService::class)->post(['journal_id' => $id], $lines, 'USD', Carbon::parse('2026-10-09'));
    }

    public function test_master_posting_marks_first_use_and_http_mutations_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('currency.rates.store'), $this->payload(['first_used_at' => '2020-01-01']))->assertSessionHasNoErrors();
        $rate = ExchangeRate::query()->firstOrFail();
        $this->assertNull($rate->first_used_at);
        $this->postWithMaster('MASTER-USED');
        $first = $rate->fresh()->first_used_at;
        $this->assertNotNull($first);
        $this->assertTrue($rate->fresh()->isUsed());
        $this->travel(1)->hours();
        $this->postWithMaster('MASTER-USED-AGAIN');
        $this->assertTrue($first->equalTo($rate->fresh()->first_used_at));
        $this->putJson(route('currency.rates.update', $rate->id), $this->payload(['rate' => '15900']))
            ->assertStatus(422)->assertJsonValidationErrors('rate');
        $this->deleteJson(route('currency.rates.destroy', $rate->id))
            ->assertStatus(422)->assertJsonValidationErrors('rate');
        $this->assertSame('15800.00000000', $rate->fresh()->rate);
        $this->get(route('currency.rates.index', ['source' => 'BI', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee($first->format('Y-m-d H:i:s'));
    }

    public function test_failed_master_posting_does_not_mark_use_or_leave_journal(): void
    {
        $user = User::factory()->create();
        $rate = app(ExchangeRateService::class)->save($this->payload(), null, $user->id);
        try {
            $this->postWithMaster('MASTER-FAILED', true);
            $this->fail('Insert detail invalid harus gagal.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertNull($rate->fresh()->first_used_at);
        $this->assertFalse($rate->fresh()->isUsed());
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_used_master_update_is_rejected_over_http(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $rate = app(ExchangeRateService::class)->save($this->payload(), null, $user->id);
        $this->postWithMaster('USED-UPDATE');
        $this->putJson(route('currency.rates.update', $rate->id), $this->payload(['rate' => '15900']))
            ->assertStatus(422)->assertJsonValidationErrors('rate');
        $this->assertSame('15800.00000000', $rate->fresh()->rate);
    }

    public function test_used_master_delete_is_rejected_over_http(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $rate = app(ExchangeRateService::class)->save($this->payload(), null, $user->id);
        $this->postWithMaster('USED-DELETE');
        $this->deleteJson(route('currency.rates.destroy', $rate->id))
            ->assertStatus(422)->assertJsonValidationErrors('rate');
        $this->assertDatabaseHas('cur_exchange_rates', ['id' => $rate->id]);
    }

    public function test_global_duplicate_is_rejected_by_service_and_http(): void
    {
        $user = User::factory()->create();
        $service = app(ExchangeRateService::class);
        $service->save($this->payload(), null, $user->id);
        try {
            $service->save($this->payload(), null, $user->id);
            $this->fail('Duplikat global harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('rate_date', $exception->errors());
        }
        $this->actingAs($user)->postJson(route('currency.rates.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('rate_date');
        $this->assertDatabaseCount('cur_exchange_rates', 1);
    }

    public function test_tax_adjacent_ranges_are_accepted_but_shared_endpoint_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $tax = $this->payload(['source' => 'KMK', 'type' => 'TAX', 'rate_date' => '2026-10-07', 'valid_until' => '2026-10-13']);
        $this->postJson(route('currency.rates.store'), $tax)->assertRedirect();
        $this->postJson(route('currency.rates.store'), array_merge($tax, ['rate_date' => '2026-10-13', 'valid_until' => '2026-10-20']))
            ->assertStatus(422)->assertJsonValidationErrors('rate_date');
        $this->postJson(route('currency.rates.store'), array_merge($tax, ['rate_date' => '2026-10-14', 'valid_until' => '2026-10-20']))
            ->assertRedirect();
        $this->assertDatabaseCount('cur_exchange_rates', 2);
    }

    public function test_guest_cannot_read_or_write_currency_routes(): void
    {
        $this->get(route('currency.index'))->assertRedirect(route('login'));
        $this->get(route('currency.rates.index'))->assertRedirect(route('login'));
        $this->post(route('currency.rates.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_jpy_quote_is_normalized_server_side_and_zero_units_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $quote = $this->payload(['currency_code' => 'JPY', 'source' => 'KMK', 'type' => 'TAX',
            'valid_until' => '2026-10-15', 'quoted_rate' => '10567.12345678', 'quoted_unit' => 100, 'rate' => '999']);
        $this->postJson(route('currency.rates.store'), array_merge($quote, ['quoted_unit' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('quoted_unit');
        $this->postJson(route('currency.rates.store'), $quote)->assertRedirect();
        $rate = ExchangeRate::query()->firstOrFail();
        $this->assertSame('105.67123457', $rate->rate);
        $this->assertSame('10567.12345678', $rate->quoted_rate);
        $this->assertSame(100, $rate->quoted_unit);
        $this->get(route('currency.rates.index', ['source' => 'KMK', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('105.67123457')->assertSee('10567.12345678');
    }

    public function test_used_quoted_master_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $quote = $this->payload(['source' => 'KMK', 'type' => 'TAX', 'valid_until' => '2026-10-15',
            'quoted_rate' => '1580000', 'quoted_unit' => 100]);
        $rate = app(ExchangeRateService::class)->save($quote, null, $user->id);
        app(FxPostingService::class)->post(['journal_id' => 'QUOTED-USED'], [
            ['account_code' => '111001', 'position' => 'DEBET', 'amount_txn' => '1'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount_txn' => '1'],
        ], 'USD', Carbon::parse('2026-10-09'), 'TAX');
        $this->putJson(route('currency.rates.update', $rate->id), array_merge($quote, ['quoted_unit' => 10]))
            ->assertStatus(422)->assertJsonValidationErrors('rate');
        $this->deleteJson(route('currency.rates.destroy', $rate->id))->assertStatus(422);
        $this->assertSame(100, $rate->fresh()->quoted_unit);
    }

    public function test_quoted_migration_rollback_rejects_original_quotes(): void
    {
        $user = User::factory()->create();
        app(ExchangeRateService::class)->save($this->payload(['source' => 'KMK', 'type' => 'TAX',
            'valid_until' => '2026-10-15', 'quoted_rate' => '15800']), null, $user->id);
        $migration = require database_path('migrations/2026_10_14_100002_add_quoted_columns_to_cur_exchange_rates.php');
        try {
            $migration->down();
            $this->fail('Kuotasi asli tidak boleh hilang.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback ditolak', $exception->getMessage());
        }
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('cur_exchange_rates', 'quoted_rate'));
    }

    public function test_create_duplicate_update_delete_and_trusted_actor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('currency.rates.store'), $this->payload(['created_by' => 999, 'updated_by' => 999]))->assertRedirect()->assertSessionHasNoErrors();
        $rate = ExchangeRate::query()->firstOrFail();
        $this->assertSame($user->id, $rate->created_by);
        $this->assertSame($user->id, $rate->updated_by);
        $this->assertSame('15800.00000000', $rate->rate);
        $this->assertFalse($rate->isUsed());
        $this->post(route('currency.rates.store'), $this->payload())->assertSessionHasErrors('rate_date');
        $this->assertDatabaseCount('cur_exchange_rates', 1);
        $this->put(route('currency.rates.update', $rate->id), $this->payload(['rate' => '15900']))->assertSessionHasNoErrors();
        $this->assertSame('15900.00000000', $rate->fresh()->rate);
        $this->delete(route('currency.rates.destroy', $rate->id))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cur_exchange_rates', 0);
    }

    public function test_invalid_rates_sources_dates_and_inactive_currency_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['0', '-1', '1e3', '1.123456789', '1234567890123', '1,234.5'] as $value) {
            $this->post(route('currency.rates.store'), $this->payload(['rate' => $value]))->assertSessionHasErrors('rate');
        }
        $this->post(route('currency.rates.store'), $this->payload(['currency_code' => 'EUR']))->assertSessionHasErrors('currency_code');
        $this->post(route('currency.rates.store'), $this->payload(['rate_date' => '2026-02-30']))->assertSessionHasErrors('rate_date');
        $this->post(route('currency.rates.store'), $this->payload(['source' => 'KMK']))->assertSessionHasErrors('rate');
        $this->post(route('currency.rates.store'), $this->payload(['type' => 'TAX', 'source' => 'KMK']))->assertSessionHasErrors('valid_until');
        $this->assertDatabaseCount('cur_exchange_rates', 0);
    }

    public function test_csv_import_valid_invalid_and_duplicate_rollback(): void
    {
        $this->actingAs(User::factory()->create());
        $valid = UploadedFile::fake()->createWithContent('rates.csv', "currency_code,rate_date,rate,type\nUSD,2026-10-09,15800,SPOT\nCNY,2026-10-09,2200,SPOT\n");
        $this->post(route('currency.rates.import'), ['file' => $valid])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cur_exchange_rates', 2);
        $bad = UploadedFile::fake()->createWithContent('bad.csv', "currency_code,rate_date,rate,type\nJPY,2026-10-09,100,SPOT\nKRW,2026-10-09,invalid,SPOT\n");
        $this->post(route('currency.rates.import'), ['file' => $bad])->assertSessionHasErrors('rate');
        $this->assertDatabaseCount('cur_exchange_rates', 2);
        $duplicate = UploadedFile::fake()->createWithContent('duplicate.csv', "currency_code,rate_date,rate,type\nJPY,2026-10-09,100,SPOT\nUSD,2026-10-09,15800,SPOT\n");
        $this->post(route('currency.rates.import'), ['file' => $duplicate])->assertSessionHasErrors('rate_date');
        $this->assertDatabaseCount('cur_exchange_rates', 2);
        $badHeader = UploadedFile::fake()->createWithContent('bad.csv', "code,rate\nUSD,15800\n");
        $this->post(route('currency.rates.import'), ['file' => $badHeader])->assertSessionHasErrors('file');
    }

    public function test_service_lookup_exact_fallback_future_and_stale_boundaries(): void
    {
        $service = app(ExchangeRateService::class);
        $actor = User::factory()->create()->id;
        $service->save($this->payload(), null, $actor);
        $this->assertSame('15800.00000000', $service->rateFor('USD', Carbon::parse('2026-10-09')));
        $this->assertSame('15800.00000000', $service->rateFor('USD', Carbon::parse('2026-10-14')));
        $this->assertSame('1.00000000', $service->rateFor('IDR', Carbon::parse('2026-10-09')));
        foreach (['2026-10-08', '2026-10-15'] as $date) {
            try {
                $service->rateFor('USD', Carbon::parse($date));
                $this->fail('Kurs di masa depan/kedaluwarsa tidak boleh dipilih.');
            } catch (ExchangeRateNotFound $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_tax_validity_two_weeks_overlap_and_expiration(): void
    {
        $this->actingAs(User::factory()->create());
        $tax = $this->payload(['type' => 'TAX', 'source' => 'KMK', 'rate_date' => '2026-10-07', 'valid_until' => '2026-10-20']);
        $this->post(route('currency.rates.store'), $tax)->assertSessionHasNoErrors();
        $this->assertSame('15800.00000000', app(ExchangeRateService::class)->rateFor('USD', Carbon::parse('2026-10-20'), 'TAX'));
        $this->post(route('currency.rates.store'), array_merge($tax, ['rate_date' => '2026-10-14', 'valid_until' => '2026-10-21']))->assertSessionHasErrors('rate_date');
        $this->expectException(ExchangeRateNotFound::class);
        app(ExchangeRateService::class)->rateFor('USD', Carbon::parse('2026-10-21'), 'TAX');
    }

    public function test_company_scope_rejects_foreign_rate_and_ignores_forged_company(): void
    {
        $user = User::factory()->create();
        $a = Company::query()->create(['code' => 'FXA', 'name' => 'A', 'base_currency' => 'IDR']);
        $b = Company::query()->create(['code' => 'FXB', 'name' => 'B', 'base_currency' => 'USD']);
        $user->companies()->attach($a->id, ['is_default' => true]);
        $this->actingAs($user)->withSession([CompanyContext::SESSION_KEY => $a->id]);
        $foreign = app(ExchangeRateService::class)->save($this->payload(['rate' => '1']), $b->id, $user->id);
        $this->put(route('currency.rates.update', $foreign->id), $this->payload())->assertNotFound();
        $this->delete(route('currency.rates.destroy', $foreign->id))->assertNotFound();
        $this->post(route('currency.rates.store'), $this->payload(['company_id' => $b->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cur_exchange_rates', ['company_id' => $a->id, 'currency_code' => 'USD']);
        $this->assertSame('1.00000000', app(ExchangeRateService::class)->rateFor('USD', Carbon::parse('2026-10-09'), 'SPOT', $b->id));
    }

    public function test_master_seed_idempotence_default_show_and_base_display(): void
    {
        $this->assertGreaterThan(150, Currency::query()->count());
        $this->assertSame(['CNY', 'IDR', 'JPY', 'KRW', 'USD'], Currency::query()->where('show', true)->orderBy('code')->pluck('code')->all());
        $this->assertSame(0, Currency::query()->findOrFail('JPY')->decimal_places);
        $this->assertSame(0, Currency::query()->findOrFail('KRW')->decimal_places);
        $this->assertSame(2, Currency::query()->findOrFail('IDR')->decimal_places);
        $this->assertSame('¥', Currency::query()->findOrFail('JPY')->symbol);
        $this->assertSame('₩', Currency::query()->findOrFail('KRW')->symbol);
        $this->actingAs(User::factory()->create());
        $this->put(route('currency.update', 'USD'), ['active' => false, 'show' => false])->assertSessionHasNoErrors();
        $this->seed(CurrencySeeder::class);
        $this->assertFalse(Currency::query()->findOrFail('USD')->show);
        $this->get(route('currency.index'))->assertOk()->assertSee(__('currency.base'));
    }

    public function test_rates_view_three_month_filter_and_three_locales(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('currency.rates.store'), $this->payload())->assertSessionHasNoErrors();
        $url = route('currency.rates.index', ['source' => 'BI', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->get($url)->assertOk()->assertSee('15800.00000000')->assertSee(__('currency.already_used'));
        $this->get(route('currency.rates.index', ['from' => '2026-01-01', 'to' => '2026-04-02']))->assertSessionHasErrors('to');
        $this->get(route('currency.rates.index', ['from' => '2026-10-09', 'to' => '2026-10-08']))->assertSessionHasErrors('to');
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $this->withSession(['locale' => $locale])->get($url)->assertOk()->assertDontSee('currency.bi_help');
            $this->assertSame(array_keys(require lang_path('id/currency.php')), array_keys(require lang_path($locale.'/currency.php')));
        }
    }

    // FIX: multicurrency — flag off tidak boleh mendaftarkan endpoint baru.
    public function test_flag_off_registers_no_currency_routes(): void
    {
        config(['platform.multicurrency_enabled' => false]);
        $count = count(Route::getRoutes());
        require base_path('routes/currency.php');
        $this->assertSame($count, count(Route::getRoutes()));
        $defaults = require config_path('platform.php');
        $this->assertFalse((bool) $defaults['multicurrency_enabled']);
    }

    // FIX: multicurrency — kurs dasar satu dan pilihan dicabut diperiksa server, bukan input bebas.
    public function test_base_rate_and_revoked_company_selection_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('currency.rates.store'), $this->payload(['currency_code' => 'IDR']))->assertSessionHasErrors('rate');
        $this->withSession([CompanyContext::SESSION_KEY => 999999])
            ->post(route('currency.rates.store'), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('cur_exchange_rates', 0);
    }
}
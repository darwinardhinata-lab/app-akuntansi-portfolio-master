<?php

namespace Tests\Feature\Currency;

use App\Http\Controllers\AccountController;
use App\Models\Account;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->actingAs(User::factory()->create());
        config(['platform.multicurrency_enabled' => true]);
        Company::query()->firstOrCreate(['code' => 'MGI'], ['name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        $this->seed(CurrencySeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['account_code' => 'BANK-UAT', 'account_name' => 'Bank USD',
            'coa_type' => 'Cash & Bank', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'currency_code' => 'USD'], $overrides);
    }

    public function test_empty_account_currency_can_be_set_over_http_and_logged(): void
    {
        $this->post(route('account.store'), $this->payload())->assertRedirect(route('account.index'));
        $this->assertDatabaseHas('accounts', ['account_code' => 'BANK-UAT', 'currency_code' => 'USD']);
        $this->assertDatabaseHas('system_logs', ['description' => 'Mata uang akun BANK-UAT: NULL → USD']);
        $this->put(route('account.update', 'BANK-UAT'), $this->payload(['currency_code' => 'CNY']))->assertRedirect();
        $this->assertDatabaseHas('system_logs', ['description' => 'Mata uang akun BANK-UAT: USD → CNY']);
        $this->put(route('account.update', 'BANK-UAT'), $this->payload(['currency_code' => '']))->assertRedirect();
        $this->assertDatabaseHas('accounts', ['account_code' => 'BANK-UAT', 'currency_code' => null]);
    }

    public function test_direct_controller_validation_rejects_base_currency_without_writing(): void
    {
        $request = Request::create('/accounts', 'POST', $this->payload(['currency_code' => 'IDR']));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(AccountController::class)->store($request);
        } finally {
            $this->assertDatabaseMissing('accounts', ['account_code' => 'BANK-UAT']);
        }
    }

    public function test_control_base_inactive_missing_and_non_cash_accounts_are_rejected(): void
    {
        foreach ([['currency_code' => 'IDR'], ['currency_code' => 'EUR'], ['currency_code' => 'ZZZ'],
            ['account_code' => config('coa.piutang_usaha')], ['account_code' => config('coa.hutang_usaha')],
            ['coa_type' => 'ASSET']] as $overrides) {
            $this->postJson(route('account.store'), $this->payload($overrides))
                ->assertStatus(422)->assertJsonValidationErrors('currency_code');
        }
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_currency_cannot_be_changed_or_cleared_after_journal_exists(): void
    {
        $this->post(route('account.store'), $this->payload())->assertRedirect();
        DB::table('journal_headers')->insert(['journal_id' => 'ACCOUNT-CURRENCY', 'transaction_date' => '2026-10-09']);
        DB::table('journal_details')->insert(['journal_id' => 'ACCOUNT-CURRENCY', 'account_code' => 'BANK-UAT',
            'position' => 'DEBET', 'amount' => '1']);
        $before = Account::query()->findOrFail('BANK-UAT')->getAttributes();
        foreach (['CNY', '', null] as $currency) {
            $this->putJson(route('account.update', 'BANK-UAT'), $this->payload(['currency_code' => $currency]))
                ->assertStatus(422)->assertJsonValidationErrors('currency_code');
            $this->assertSame($before, Account::query()->findOrFail('BANK-UAT')->getAttributes());
        }
        $this->put(route('account.update', 'BANK-UAT'), $this->payload(['account_name' => 'Renamed']))->assertRedirect();
        $this->assertDatabaseHas('accounts', ['account_code' => 'BANK-UAT', 'currency_code' => 'USD', 'account_name' => 'Renamed']);
    }

    public function test_flag_off_hides_and_ignores_currency_field_on_create_and_update(): void
    {
        config(['platform.multicurrency_enabled' => false]);
        $this->get(route('account.create'))->assertOk()->assertDontSee('name="currency_code"', false);
        $this->post(route('account.store'), $this->payload())->assertRedirect();
        $this->get(route('account.edit', 'BANK-UAT'))->assertOk()->assertDontSee('name="currency_code"', false);
        $this->put(route('account.update', 'BANK-UAT'), $this->payload(['currency_code' => 'CNY']))->assertRedirect();
        $this->assertDatabaseHas('accounts', ['account_code' => 'BANK-UAT', 'currency_code' => null]);
        $this->assertSame(0, DB::table('system_logs')->where('description', 'like', 'Mata uang akun%')->count());
    }

    public function test_golden_idr_store_and_update_are_identical_with_flag_on_or_off(): void
    {
        foreach ([false, true] as $flag) {
            config(['platform.multicurrency_enabled' => $flag]);
            $code = $flag ? 'IDR-ON' : 'IDR-OFF';
            $payload = $this->payload(['account_code' => $code, 'coa_type' => 'ASSET', 'currency_code' => null]);
            $this->post(route('account.store'), $payload)->assertRedirect();
            $payload['account_name'] = 'Golden IDR';
            $this->put(route('account.update', $code), $payload)->assertRedirect();
            $attributes = Account::query()->findOrFail($code)->only(['account_name', 'coa_type', 'normal_balance', 'report_pos', 'currency_code']);
            $this->assertSame(['account_name' => 'Golden IDR', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET',
                'report_pos' => 'NERACA', 'currency_code' => null], $attributes);
        }
    }

    public function test_enabled_forms_render_active_currency_options(): void
    {
        $this->get(route('account.create'))->assertOk()->assertSee('name="currency_code"', false)->assertSee('value="USD"', false)->assertDontSee('value="EUR"', false);
        $this->post(route('account.store'), $this->payload())->assertRedirect();
        $this->get(route('account.edit', 'BANK-UAT'))->assertOk()->assertSee('name="currency_code"', false);
    }
}
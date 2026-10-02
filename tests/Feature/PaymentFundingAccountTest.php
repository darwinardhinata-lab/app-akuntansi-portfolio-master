<?php

namespace Tests\Feature;

use App\Support\PaymentFundingAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFundingAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_idr_accounts_are_selectable_without_fallback(): void
    {
        foreach (['111001', '111002', '111101', '111102', '111103', '111201', '112100'] as $code) {
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => 'Account '.$code,
                'coa_type' => 'Cash & Bank', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertSame(['111001', '111002', '111101', '111102', '111103'], PaymentFundingAccount::options()->pluck('account_code')->all());
        $this->assertSame('111102', PaymentFundingAccount::resolve('111102'));
        foreach (['BANK', 'PENDING', '111201', '112100', '99999'] as $invalid) {
            try {
                PaymentFundingAccount::resolve($invalid);
                $this->fail('Invalid funding source accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('jenis_transaksi', $e->errors());
            }
        }
        DB::table('accounts')->where('account_code', '111102')->update(['normal_balance' => 'KREDIT']);
        $this->assertFalse(PaymentFundingAccount::options()->contains('account_code', '111102'));
    }
}

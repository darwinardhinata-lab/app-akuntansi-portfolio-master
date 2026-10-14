<?php

namespace Tests\Feature\Currency;

use App\Modules\Currency\Services\FxPostingService;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BaseCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $code, bool $active = true, string $base = 'IDR'): Company
    {
        return Company::query()->create(['code' => $code, 'name' => $code,
            'active' => $active, 'base_currency' => $base]);
    }

    public function test_non_mgi_company_and_inactive_companies_are_supported(): void
    {
        Company::query()->delete();
        $company = $this->company('ABC', true, 'CNY');
        $this->company('INACTIVE', false);
        config(['customs.enabled' => true, 'platform.legacy_sync_enabled' => true]);
        $this->assertSame('CNY', FxPostingService::baseCurrency());
        $this->assertSame('CNY', FxPostingService::baseCurrency((int) $company->id));
    }

    public function test_zero_active_companies_throws_runtime_exception(): void
    {
        Company::query()->update(['active' => false]);
        $this->assertAmbiguousCompanyRejected();
    }

    public function test_two_active_companies_throw_runtime_exception(): void
    {
        Company::query()->delete();
        $this->company('ABC');
        $this->company('DEF');
        $this->assertAmbiguousCompanyRejected();
    }

    private function assertAmbiguousCompanyRejected(): void
    {
        try {
            FxPostingService::baseCurrency();
            $this->fail('Perusahaan aktif ambigu harus ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            $this->assertSame('Mata uang dasar tidak dapat ditentukan: diperlukan tepat satu perusahaan aktif.', $exception->getMessage());
        }
    }

    public function test_empty_or_invalid_base_currency_throws_runtime_exception(): void
    {
        Company::query()->delete();
        $company = $this->company('ABC');
        foreach (['', 'usd', 'US', 'USDD'] as $base) {
            $company->update(['base_currency' => $base]);
            try {
                FxPostingService::baseCurrency();
                $this->fail('Mata uang dasar invalid harus ditolak.');
            } catch (RuntimeException $exception) {
                $this->assertSame(RuntimeException::class, get_class($exception));
                $this->assertStringContainsString('Mata uang dasar', $exception->getMessage());
            }
        }
    }
}
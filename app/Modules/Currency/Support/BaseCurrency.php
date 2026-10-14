<?php

namespace App\Modules\Currency\Support;

use App\Modules\Platform\Models\Company;
use RuntimeException;

class BaseCurrency
{
    public static function code(?int $companyId = null): string
    {
        if ($companyId !== null) {
            $company = Company::query()->whereKey($companyId)->where('active', true)->firstOrFail();
        } else {
            $companies = Company::query()->where('active', true)->limit(2)->get();
            if ($companies->count() !== 1) {
                throw new RuntimeException('Mata uang dasar tidak dapat ditentukan: diperlukan tepat satu perusahaan aktif.');
            }
            $company = $companies->first();
        }

        if (! preg_match('/^[A-Z]{3}$/D', (string) $company->base_currency)) {
            throw new RuntimeException('Mata uang dasar perusahaan tidak valid: diperlukan kode 3 huruf besar.');
        }

        return $company->base_currency;
    }
}
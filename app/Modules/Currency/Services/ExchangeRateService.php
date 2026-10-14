<?php

// FIX: multicurrency — lookup dan mutasi kurs terpusat, terkunci pada baris master mata uang.
namespace App\Modules\Currency\Services;

use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Models\ExchangeRate;
use App\Modules\Currency\Support\Money;
use App\Modules\Platform\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExchangeRateService
{
    public function rateFor(string $currency, Carbon $date, string $type = 'SPOT', ?int $companyId = null): string
    {
        return $this->lookup($currency, $date, $type, $companyId)['rate'];
    }

    public function lookup(string $currency, Carbon $date, string $type = 'SPOT', ?int $companyId = null): array
    {
        $base = FxPostingService::baseCurrency($companyId);
        if ($currency === $base) {
            return ['rate' => '1.00000000', 'source' => 'BASE', 'found' => true];
        }
        if (! in_array($type, ['SPOT', 'TAX', 'AVERAGE'], true) || ! Currency::query()->whereKey($currency)->where('active', true)->exists()) {
            throw new ExchangeRateNotFound(__('currency.missing'));
        }
        $query = ExchangeRate::query()->where('company_id', $companyId)->where('currency_code', $currency)
            ->where('type', $type)->whereDate('rate_date', '<=', $date->toDateString());
        if ($type === 'TAX') {
            $query->whereDate('valid_until', '>=', $date->toDateString());
        } else {
            $query->whereDate('rate_date', '>=', $date->copy()->subDays((int) config('platform.fx_rate_max_stale_days', 5))->toDateString());
        }
        $rate = $query->orderByDesc('rate_date')->first();
        if (! $rate) {
            throw new ExchangeRateNotFound(__('currency.missing'));
        }

        return ['rate' => $rate->rate, 'source' => $rate->source, 'found' => true];
    }

    public function save(array $data, ?int $companyId, int $actorId, ?int $id = null): ExchangeRate
    {
        return DB::transaction(function () use ($data, $companyId, $actorId, $id) {
            $data = Validator::make($data, [
                'currency_code' => ['required', 'string', Rule::exists('cur_currencies', 'code')->where('active', true)],
                'rate' => ['required_without:quoted_rate', 'nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/D'],
                'quoted_rate' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/D'],
                'quoted_unit' => ['sometimes', 'required', 'integer', 'min:1', 'max:4294967295'],
                'rate_date' => ['required', 'date_format:Y-m-d'],
                'type' => ['required', Rule::in(['SPOT', 'TAX', 'AVERAGE'])],
                'source' => ['required', Rule::in(['BI', 'KMK', 'MANUAL'])],
                'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:rate_date', 'required_if:type,TAX'],
            ])->validate();
            if (($data['quoted_rate'] ?? null) !== null) {
                if ($data['source'] !== 'KMK' || $data['type'] !== 'TAX' || Money::compare($data['quoted_rate'], '0') <= 0) {
                    throw ValidationException::withMessages(['quoted_rate' => __('currency.invalid')]);
                }
                $data['quoted_unit'] = (int) ($data['quoted_unit'] ?? 1);
                $data['rate'] = Money::div($data['quoted_rate'], (string) $data['quoted_unit'], 8);
                $data['quoted_rate'] = Money::round($data['quoted_rate'], 8);
            } else {
                $data['quoted_rate'] = null;
                $data['quoted_unit'] = 1;
            }
            // Serialize writers even for global scopes (NULL is not unique in MySQL indexes).
            $currency = Currency::query()->whereKey($data['currency_code'])->lockForUpdate()->firstOrFail();
            if (! $currency->active || $currency->decimal_places === null) {
                throw ValidationException::withMessages(['currency_code' => __('currency.invalid')]);
            }
            if (Money::compare($data['rate'], '0') <= 0
                || ($data['source'] === 'KMK' && $data['type'] !== 'TAX')
                || ($data['source'] === 'BI' && $data['type'] !== 'SPOT')) {
                throw ValidationException::withMessages(['rate' => __('currency.invalid')]);
            }
            // FIX: multicurrency — kurs mata uang dasar hanya boleh satu, sesuai lookup rateFor.
            $base = $companyId === null ? 'IDR' : Company::query()->findOrFail($companyId)->base_currency;
            if ($data['currency_code'] === $base && Money::compare($data['rate'], '1') !== 0) {
                throw ValidationException::withMessages(['rate' => __('currency.invalid')]);
            }
            $rate = $id === null ? new ExchangeRate : ExchangeRate::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($id);
            if ($rate->exists && $rate->isUsed()) {
                throw ValidationException::withMessages(['rate' => __('currency.used')]);
            }
            $duplicates = ExchangeRate::query()->where('company_id', $companyId)->where('currency_code', $data['currency_code'])->where('type', $data['type']);
            if ($id !== null) {
                $duplicates->whereKeyNot($id);
            }
            if ($data['type'] === 'TAX') {
                $duplicates->whereDate('rate_date', '<=', $data['valid_until'])->whereDate('valid_until', '>=', $data['rate_date']);
            } else {
                $duplicates->whereDate('rate_date', $data['rate_date']);
            }
            if ($duplicates->exists()) {
                throw ValidationException::withMessages(['rate_date' => __('currency.duplicate')]);
            }
            $data['valid_until'] = $data['type'] === 'TAX' ? $data['valid_until'] : null;
            $data['rate'] = Money::round($data['rate'], 8);
            $rate->fill($data);
            $rate->company_id = $companyId;
            if (! $rate->exists) {
                $rate->created_by = $actorId;
            }
            $rate->updated_by = $actorId;
            $rate->save();

            return $rate;
        });
    }

    public function delete(int $id, ?int $companyId): void
    {
        DB::transaction(function () use ($id, $companyId) {
            $rate = ExchangeRate::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($id);
            // First-use marker is committed atomically with the journal posting.
            if ($rate->isUsed()) {
                throw ValidationException::withMessages(['rate' => __('currency.used')]);
            }
            $rate->delete();
        });
    }
}
<?php

// FIX: multicurrency — validasi FX terpakai ulang dan pembungkus tanpa mengubah validator IDR.
namespace App\Modules\Currency\Services;

use App\Models\JournalHeader;
use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Models\ExchangeRate;
use App\Modules\Currency\Support\BaseCurrency;
use App\Modules\Currency\Support\Money;
use App\Support\PostingService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FxPostingService
{
    // FIX: multicurrency — satu sumber mata uang dasar; tanpa fallback IDR tersembunyi.
    public static function baseCurrency(?int $companyId = null): string
    {
        return BaseCurrency::code($companyId);
    }

    // FIX: multicurrency — validator manual tunggal menghitung snapshot dan menyerap satu sen pembulatan.
    public static function prepareManualJournal(array $lines, string $currency, string $rate, Carbon $date, ?int $companyId = null): array
    {
        if (! config('platform.multicurrency_enabled')) {
            throw new RuntimeException('Multicurrency belum diaktifkan.');
        }
        $masterCurrency = self::requireForeignCurrency($currency, $companyId);
        $scale = $masterCurrency->decimal_places ?? 2;
        if ($scale > 4 || ! preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', $rate)
            || Money::compare($rate, '0') <= 0 || count($lines) < 2) {
            throw new RuntimeException('Kurs atau baris jurnal valas tidak valid.');
        }
        $txnTotals = ['DEBET' => '0.0000', 'KREDIT' => '0.0000'];
        $baseTotals = ['DEBET' => '0.00', 'KREDIT' => '0.00'];
        foreach ($lines as &$line) {
            $position = $line['position'] ?? null;
            $txn = $line['amount_txn'] ?? null;
            if (! in_array($position, ['DEBET', 'KREDIT'], true)
                || ! is_string($txn) || ! preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $txn)
                || Money::compare($txn, '0') <= 0 || Money::compare($txn, Money::round($txn, $scale)) !== 0) {
                throw new RuntimeException('Posisi atau nominal transaksi valas tidak valid.');
            }
            if ($position === 'DEBET' && ($line['account_code'] ?? null) === config('coa.aset_tetap')) {
                throw new RuntimeException('aset valas belum didukung');
            }
            $line['amount'] = Money::convert($txn, $rate, 2);
            $line['amount_txn'] = Money::round($txn, 4);
            $line['currency_code'] = $currency;
            $line['exchange_rate'] = Money::round($rate, 8);
            $txnTotals[$position] = Money::add($txnTotals[$position], $txn, 4);
            $baseTotals[$position] = Money::add($baseTotals[$position], $line['amount']);
        }
        unset($line);
        if (Money::compare($txnTotals['DEBET'], $txnTotals['KREDIT']) !== 0) {
            throw new RuntimeException('Jurnal tidak balance dalam mata uang transaksi.');
        }
        $remainder = Money::sub($baseTotals['DEBET'], $baseTotals['KREDIT']);
        if (Money::compare(ltrim($remainder, '-'), '0.01') > 0) {
            throw new RuntimeException('Sisa pembulatan jurnal valas terlalu besar: '.$remainder.'.');
        }
        if (Money::compare($remainder, '0') !== 0) {
            $side = Money::compare($remainder, '0') > 0 ? 'KREDIT' : 'DEBET';
            $largest = null;
            foreach ($lines as $index => $line) {
                if ($line['position'] === $side && ($largest === null
                    || Money::compare($line['amount'], $lines[$largest]['amount']) > 0)) {
                    $largest = $index;
                }
            }
            $lines[$largest]['amount'] = Money::add($lines[$largest]['amount'], ltrim($remainder, '-'));
        }

        return ['lines' => $lines, 'currency_code' => $currency, 'exchange_rate' => Money::round($rate, 8),
            'transaction_date' => $date->toDateString()];
    }

    // FIX: multicurrency — pengecualian transaksi campuran hanya untuk saldo awal OPENING.
    public static function prepareOpeningLines(array $lines, string $journalType, ?int $companyId = null): array
    {
        if ($journalType !== 'OPENING' || ! config('platform.multicurrency_enabled')) {
            throw new RuntimeException('Saldo awal valas hanya diizinkan untuk jurnal OPENING saat multicurrency aktif.');
        }
        $base = self::baseCurrency($companyId);
        $totals = ['DEBET' => '0.00', 'KREDIT' => '0.00'];
        foreach ($lines as &$line) {
            $position = $line['position'] ?? null;
            if (! in_array($position, ['DEBET', 'KREDIT'], true)) {
                throw new RuntimeException('Posisi saldo awal tidak valid.');
            }
            $code = $line['currency_code'] ?? $base;
            if ($code !== $base) {
                $currency = self::requireForeignCurrency($code, $companyId);
                if (in_array($line['account_code'] ?? null, [config('coa.piutang_usaha'), config('coa.hutang_usaha')], true)) {
                    throw new RuntimeException('masukkan sebagai dokumen terbuka (invoice/bill)');
                }
                $txn = $line['amount_txn'] ?? null;
                $rate = $line['exchange_rate'] ?? null;
                if (! is_string($txn) || ! preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $txn)
                    || Money::compare($txn, '0') <= 0
                    || Money::compare($txn, Money::round($txn, $currency->decimal_places ?? 2)) !== 0
                    || ! is_string($rate) || ! preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', $rate)
                    || Money::compare($rate, '0') <= 0) {
                    throw new RuntimeException('Nominal atau kurs saldo awal valas tidak valid.');
                }
                $converted = Money::convert($txn, $rate);
                if (isset($line['amount'])) {
                    if (! is_string($line['amount']) || ! preg_match('/^\d{1,18}(?:\.\d{1,2})?$/D', $line['amount'])) {
                        throw new RuntimeException('Nominal dasar saldo awal tidak valid.');
                    }
                    $difference = Money::sub($line['amount'], $converted);
                    if (Money::compare(ltrim($difference, '-'), '0.01') > 0) {
                        throw new RuntimeException('Deviasi konversi FX melebihi 0,01.');
                    }
                }
                $line['amount'] = $converted;
                $line['amount_txn'] = Money::round($txn, 4);
                $line['exchange_rate'] = Money::round($rate, 8);
            } else {
                if (! is_string($line['amount'] ?? null) || ! preg_match('/^\d{1,18}(?:\.\d{1,2})?$/D', $line['amount'])
                    || Money::compare($line['amount'], '0') <= 0 || ($line['amount_txn'] ?? null) !== null) {
                    throw new RuntimeException('Nominal saldo awal mata uang dasar tidak valid.');
                }
                $line['amount'] = Money::round($line['amount']);
                $line['amount_txn'] = null;
                $line['exchange_rate'] = '1.00000000';
            }
            $line['currency_code'] = $code;
            $totals[$position] = Money::add($totals[$position], $line['amount']);
        }
        unset($line);
        if (! $lines || Money::compare($totals['DEBET'], $totals['KREDIT']) !== 0) {
            throw new RuntimeException('Saldo awal tidak balance dalam mata uang dasar: '.Money::sub($totals['DEBET'], $totals['KREDIT']).'.');
        }

        return $lines;
    }

    public static function requireForeignCurrency(string $code, ?int $companyId = null): Currency
    {
        $currency = Currency::query()->whereKey($code)->sharedLock()->first();
        if (! $currency) {
            throw new RuntimeException('Mata uang FX tidak ditemukan.');
        }
        if (! $currency->active) {
            throw new RuntimeException('Mata uang FX tidak aktif.');
        }
        $base = self::baseCurrency($companyId);
        if ($code === $base) {
            throw new RuntimeException('Mata uang FX sama dengan mata uang dasar perusahaan.');
        }

        return $currency;
    }

    // Caller holds the posting transaction until journal inserts and markRateUsed complete.
    public function resolvePostingRate(string $currency, Carbon $date, ?string $requestedRate = null, ?string $reason = null, ?int $companyId = null): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Resolusi kurs posting harus berada dalam transaksi database.');
        }
        self::requireForeignCurrency($currency, $companyId);
        $master = ExchangeRate::query()->where('company_id', $companyId)->where('currency_code', $currency)
            ->where('type', 'SPOT')->whereDate('rate_date', '<=', $date->toDateString())
            ->whereDate('rate_date', '>=', $date->copy()->subDays((int) config('platform.fx_rate_max_stale_days', 5))->toDateString())
            ->orderByDesc('rate_date')->lockForUpdate()->first();
        $masterRate = $master?->rate;
        $rate = $requestedRate ?? $masterRate;
        if ($rate === null || $rate === '') {
            throw new RuntimeException('Kurs SPOT tidak tersedia; isi kurs override dan alasannya.');
        }
        if (! preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', $rate) || Money::compare($rate, '0') <= 0) {
            throw new RuntimeException('Kurs harus positif dengan maksimal 8 desimal.');
        }
        $override = $masterRate === null || Money::compare($rate, $masterRate) !== 0;
        if ($override && trim((string) $reason) === '') {
            throw new RuntimeException('Alasan override kurs wajib diisi.');
        }

        return ['rate' => Money::round($rate, 8), 'master_rate' => $masterRate,
            'master_id' => $override ? null : $master?->getKey(), 'override' => $override,
            'reason' => trim((string) $reason), 'currency_code' => $currency];
    }

    public function markRateUsed(array $metadata): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Penandaan kurs harus berada dalam transaksi posting.');
        }
        if (($metadata['master_id'] ?? null) !== null && ! ($metadata['override'] ?? true)) {
            ExchangeRate::query()->whereKey($metadata['master_id'])->whereNull('first_used_at')
                ->update(['first_used_at' => now()]);
        }
    }

    public static function validateLines(array $lines, array $fx): array
    {
        if (! is_string($fx['currency'] ?? null) || ! preg_match('/^[A-Z]{3}$/D', $fx['currency'])
            || ! is_string($fx['rate'] ?? null) || ! preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', $fx['rate'])
            || Money::compare($fx['rate'], '0') <= 0) {
            throw new RuntimeException('Mata uang atau kurs FX tidak valid.');
        }
        $currency = self::requireForeignCurrency($fx['currency'], $fx['company_id'] ?? null);
        if ($currency->decimal_places === null || $currency->decimal_places > 4) {
            throw new RuntimeException('Mata uang FX tidak aktif atau presisinya tidak didukung.');
        }
        if (! $lines) {
            throw new RuntimeException('Posting FX harus memiliki baris jurnal.');
        }
        $totals = ['DEBET' => '0.0000', 'KREDIT' => '0.0000'];
        foreach ($lines as &$line) {
            if (! in_array($line['position'] ?? null, ['DEBET', 'KREDIT'], true)
                || ! is_string($line['amount'] ?? null) || ! preg_match('/^\d{1,18}(?:\.\d{1,2})?$/D', $line['amount'])) {
                throw new RuntimeException('Posisi atau amount FX harus desimal string yang valid.');
            }
            if (array_key_exists('fx_adjustment', $line) && ! is_bool($line['fx_adjustment'])) {
                throw new RuntimeException('Penanda penyesuaian FX harus boolean.');
            }
            $adjustment = ($line['fx_adjustment'] ?? false) === true;
            unset($line['fx_adjustment']);
            if ($adjustment) {
                if (($line['amount_txn'] ?? null) !== null) {
                    throw new RuntimeException('Baris penyesuaian FX tidak boleh membawa amount_txn.');
                }
                $line['currency_code'] = self::baseCurrency($fx['company_id'] ?? null);
                $line['amount_txn'] = null;
                $line['exchange_rate'] = '1.00000000';
                continue;
            }
            $txn = $line['amount_txn'] ?? null;
            if (! is_string($txn) || ! preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $txn)
                || Money::compare($txn, Money::round($txn, $currency->decimal_places)) !== 0
                || (isset($line['currency_code']) && $line['currency_code'] !== $fx['currency'])
                || (isset($line['exchange_rate']) && Money::compare((string) $line['exchange_rate'], $fx['rate']) !== 0)) {
                throw new RuntimeException('Snapshot atau amount_txn FX tidak valid.');
            }
            $difference = Money::sub($line['amount'], Money::convert($txn, $fx['rate']));
            if (Money::compare(ltrim($difference, '-'), '0.01') > 0) {
                throw new RuntimeException('Deviasi konversi FX melebihi 0,01.');
            }
            $line['currency_code'] = $fx['currency'];
            $line['exchange_rate'] = Money::round($fx['rate'], 8);
            $line['amount_txn'] = Money::round($txn, 4);
            $totals[$line['position']] = Money::add($totals[$line['position']], $txn, 4);
        }
        unset($line);
        if (Money::compare($totals['DEBET'], $totals['KREDIT']) !== 0) {
            throw new RuntimeException('Jurnal tidak balance dalam mata uang transaksi.');
        }

        return $lines;
    }

    public function post(array $header, array $lines, string $currency, Carbon $date, string $type = 'SPOT', ?int $companyId = null, string|Closure|null $message = null): JournalHeader
    {
        if (! config('platform.multicurrency_enabled')) {
            throw new RuntimeException('Multicurrency belum diaktifkan.');
        }
        return DB::transaction(function () use ($header, $lines, $currency, $date, $type, $companyId, $message) {
            // Lock order matches rate updates: currency first, then the selected master rate.
            self::requireForeignCurrency($currency, $companyId);
            $base = self::baseCurrency($companyId);
            $master = $currency === $base ? null : ExchangeRate::query()
                ->where('company_id', $companyId)->where('currency_code', $currency)->where('type', $type)
                ->whereDate('rate_date', '<=', $date->toDateString())
                ->when($type === 'TAX', fn ($query) => $query->whereDate('valid_until', '>=', $date->toDateString()))
                ->orderByDesc('rate_date')->lockForUpdate()->first();
            // Existing lookup remains authoritative for stale dates and TAX validity.
            $rate = app(ExchangeRateService::class)->rateFor($currency, $date, $type, $companyId);
            foreach ($lines as &$line) {
                if (($line['fx_adjustment'] ?? false) !== true) {
                    $line['amount'] = Money::convert($line['amount_txn'], $rate);
                }
            }
            unset($line);
            $journal = PostingService::post($header, $lines, $message, ['currency' => $currency, 'rate' => $rate, 'company_id' => $companyId]);
            if ($master !== null) {
                ExchangeRate::query()->whereKey($master->getKey())->whereNull('first_used_at')
                    ->update(['first_used_at' => now()]);
            }
            return $journal;
        });
    }
}
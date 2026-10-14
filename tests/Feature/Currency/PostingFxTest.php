<?php

// FIX: multicurrency — uji validasi FX, rollback atomik, pembungkus dan command audit nyata.
namespace Tests\Feature\Currency;

use App\Modules\Currency\Services\ExchangeRateService;
use App\Modules\Currency\Services\FxPostingService;
use App\Modules\Currency\Support\Money;
use App\Models\User;
use App\Support\PostingService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostingFxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.multicurrency_enabled' => true]);
        $this->seed(CurrencySeeder::class);
        // FIX: multicurrency — sumber mata uang dasar eksplisit dalam fixture.
        \App\Modules\Platform\Models\Company::query()->firstOrCreate(['code' => 'MGI'],
            ['name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
    }

    private function header(): array
    {
        return ['journal_id' => 'FX-TEST', 'transaction_date' => '2026-10-09', 'journal_type' => 'IMPORT', 'evidence_number' => 'FX-EVIDENCE'];
    }

    // FIX: multicurrency — perusahaan operasional non-IDR tidak boleh memakai fallback IDR.
    public function test_base_currency_reads_operational_company_and_rejects_base_as_foreign(): void
    {
        $company = \App\Modules\Platform\Models\Company::query()->where('code', 'MGI')->firstOrFail();
        $company->update(['base_currency' => 'CNY']);
        $this->assertSame('CNY', FxPostingService::baseCurrency());
        $this->assertSame('CNY', FxPostingService::baseCurrency($company->id));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mata uang dasar');
        FxPostingService::requireForeignCurrency('CNY');
    }

    private function lines(): array
    {
        return [
            ['account_code' => '111001', 'position' => 'DEBET', 'amount' => '15800000.00', 'amount_txn' => '1000.00'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '15800000.00', 'amount_txn' => '1000.00'],
        ];
    }

    public function test_manual_rate_resolution_marks_master_only_and_rolls_back_with_caller(): void
    {
        $user = User::factory()->create();
        $master = app(ExchangeRateService::class)->save(['currency_code' => 'USD', 'rate' => '15800',
            'rate_date' => '2026-10-09', 'type' => 'SPOT', 'source' => 'BI'], null, $user->id);
        $service = app(FxPostingService::class);
        DB::transaction(function () use ($service, $master) {
            $metadata = $service->resolvePostingRate('USD', Carbon::parse('2026-10-09'), '16000', 'Kurs kontrak');
            $this->assertTrue($metadata['override']);
            $service->markRateUsed($metadata);
            $this->assertNull($master->fresh()->first_used_at);
        });
        try {
            DB::transaction(function () use ($service) {
                $service->markRateUsed($service->resolvePostingRate('USD', Carbon::parse('2026-10-09')));
                throw new \RuntimeException('Simulasi insert gagal');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi insert gagal', $exception->getMessage());
        }
        $this->assertNull($master->fresh()->first_used_at);
        DB::transaction(function () use ($service) {
            $metadata = $service->resolvePostingRate('USD', Carbon::parse('2026-10-09'), '15800.00000000');
            $this->assertFalse($metadata['override']);
            $service->markRateUsed($metadata);
        });
        $this->assertNotNull($master->fresh()->first_used_at);
    }

    public function test_manual_rate_override_requires_reason_and_missing_rate_has_clear_message(): void
    {
        $service = app(FxPostingService::class);
        foreach ([[null, 'tidak tersedia'], ['15800', 'Alasan override']] as [$rate, $message]) {
            try {
                DB::transaction(fn () => $service->resolvePostingRate('USD', Carbon::parse('2026-10-09'), $rate));
                $this->fail('Kurs tanpa master/alasan harus ditolak.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('transaksi database');
        // RefreshDatabase holds an outer transaction; explicitly simulate a caller without one.
        DB::partialMock()->shouldReceive('transactionLevel')->once()->andReturn(0);
        $service->resolvePostingRate('USD', Carbon::parse('2026-10-09'), '15800', 'Manual');
    }

    public function test_manual_preparation_preserves_snapshots_and_absorbs_one_cent_on_largest_short_side(): void
    {
        $prepared = FxPostingService::prepareManualJournal([
            ['account_code' => '111001', 'position' => 'DEBET', 'amount_txn' => '0.02'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount_txn' => '0.01'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount_txn' => '0.01'],
        ], 'USD', '0.5', Carbon::parse('2026-10-09'));
        $this->assertSame('USD', $prepared['currency_code']);
        $this->assertSame('0.50000000', $prepared['exchange_rate']);
        $this->assertSame('0.02', $prepared['lines'][0]['amount']);
        foreach ($prepared['lines'] as $line) {
            $this->assertSame('USD', $line['currency_code']);
            $this->assertSame('0.50000000', $line['exchange_rate']);
        }
        $this->assertSame('0.02', Money::add($prepared['lines'][1]['amount'], $prepared['lines'][2]['amount']));
        $this->assertSame('0.0200', $prepared['lines'][0]['amount_txn']);
    }

    public function test_manual_preparation_rejects_two_cent_rounding_txn_imbalance_and_foreign_assets(): void
    {
        $date = Carbon::parse('2026-10-09');
        $split = [['account_code' => '111001', 'position' => 'DEBET', 'amount_txn' => '0.04']];
        for ($i = 0; $i < 4; $i++) {
            $split[] = ['account_code' => '211001', 'position' => 'KREDIT', 'amount_txn' => '0.01'];
        }
        $imbalanced = $this->lines();
        $imbalanced[1]['amount_txn'] = '999.99';
        $asset = $this->lines();
        $asset[0]['account_code'] = config('coa.aset_tetap');
        foreach ([[$split, '0.5', '-0.02'], [$imbalanced, '15800', 'mata uang transaksi'],
            [$asset, '15800', 'aset valas belum didukung']] as [$lines, $rate, $message]) {
            try {
                FxPostingService::prepareManualJournal($lines, 'USD', $rate, $date);
                $this->fail('Jurnal manual invalid harus ditolak.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    private function fx(): array
    {
        return ['currency' => 'USD', 'rate' => '15800.00000000'];
    }

    public function test_opening_preparation_allows_mixed_currency_without_transaction_balance(): void
    {
        $lines = FxPostingService::prepareOpeningLines([
            ['account_code' => '111001', 'position' => 'DEBET', 'currency_code' => 'USD',
                'amount_txn' => '1000.00', 'exchange_rate' => '15800'],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '15800000.00'],
        ], 'OPENING');
        $this->assertSame('15800000.00', $lines[0]['amount']);
        $this->assertSame('1000.0000', $lines[0]['amount_txn']);
        $this->assertSame('15800.00000000', $lines[0]['exchange_rate']);
        $this->assertSame('IDR', $lines[1]['currency_code']);
        $this->assertNull($lines[1]['amount_txn']);
        $this->assertSame('1.00000000', $lines[1]['exchange_rate']);
    }

    public function test_opening_preparation_rejects_wrong_type_deviation_control_accounts_and_disabled_flag(): void
    {
        $lines = [
            ['account_code' => '111001', 'position' => 'DEBET', 'currency_code' => 'USD',
                'amount_txn' => '1000.00', 'exchange_rate' => '15800'],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '15800000.00'],
        ];
        $deviation = $lines;
        $deviation[0]['amount'] = '15800000.02';
        $control = $lines;
        $control[0]['account_code'] = config('coa.piutang_usaha');
        foreach ([[$lines, 'MANUAL', 'OPENING'], [$deviation, 'OPENING', 'Deviasi'],
            [$control, 'OPENING', 'dokumen terbuka']] as [$input, $type, $message]) {
            try {
                FxPostingService::prepareOpeningLines($input, $type);
                $this->fail('Saldo awal invalid harus ditolak.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
        config(['platform.multicurrency_enabled' => false]);
        $this->expectException(\RuntimeException::class);
        FxPostingService::prepareOpeningLines($lines, 'OPENING');
    }

    public function test_usd_post_persists_snapshots_and_passes_read_only_audit(): void
    {
        $header = PostingService::post($this->header(), $this->lines(), null, $this->fx())->fresh();
        $this->assertSame('USD', $header->currency_code);
        foreach (DB::table('journal_details')->get() as $row) {
            $this->assertSame('USD', $row->currency_code);
            $this->assertSame(0, bccomp((string) $row->amount_txn, '1000', 4));
            $this->assertSame(0, bccomp((string) $row->exchange_rate, '15800', 8));
        }
        $this->artisan('jurnal:check-balance', ['start_date' => '2026-10-09', 'end_date' => '2026-10-09', '--fx' => true])
            ->expectsOutputToContain('FX LULUS')->assertExitCode(0);
    }

    public function test_missing_inactive_and_company_base_currencies_are_rejected_before_writes(): void
    {
        $company = \App\Modules\Platform\Models\Company::query()->where('code', 'MGI')->firstOrFail();
        $cases = [
            ['ZZZ', null, 'tidak ditemukan'],
            ['EUR', null, 'tidak aktif'],
            ['IDR', null, 'mata uang dasar'],
            ['USD', $company->id, 'mata uang dasar'],
        ];
        foreach ($cases as [$code, $companyId, $message]) {
            if ($companyId !== null) {
                $company->update(['base_currency' => 'USD']);
            }
            foreach (['direct', 'wrapper'] as $path) {
                try {
                    if ($path === 'direct') {
                        PostingService::post($this->header(), $this->lines(), null,
                            ['currency' => $code, 'rate' => '1', 'company_id' => $companyId]);
                    } else {
                        app(FxPostingService::class)->post($this->header(), $this->lines(), $code,
                            Carbon::parse('2026-10-09'), 'SPOT', $companyId);
                    }
                    $this->fail('Mata uang invalid harus ditolak.');
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString($message, $exception->getMessage());
                }
                $this->assertDatabaseCount('journal_headers', 0);
                $this->assertDatabaseCount('journal_details', 0);
            }
        }
    }

    public function test_invalid_txn_conversion_snapshot_precision_and_flag_fail_before_writes(): void
    {
        $cases = [];
        $txn = $this->lines(); $txn[1]['amount_txn'] = '999.99'; $cases[] = [$txn, $this->fx()];
        $deviation = $this->lines(); $deviation[0]['amount'] = '15800000.02'; $cases[] = [$deviation, $this->fx()];
        $snapshot = $this->lines(); $snapshot[0]['currency_code'] = 'CNY'; $cases[] = [$snapshot, $this->fx()];
        $precision = $this->lines(); $precision[0]['amount_txn'] = '1000.001'; $cases[] = [$precision, $this->fx()];
        $cases[] = [$this->lines(), ['currency' => 'USD', 'rate' => '0']];
        $cases[] = [$this->lines(), ['currency' => 'EUR', 'rate' => '15800']];
        foreach ($cases as [$lines, $fx]) {
            try {
                PostingService::post($this->header(), $lines, null, $fx);
                $this->fail('Posting FX invalid harus ditolak.');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertDatabaseCount('journal_headers', 0);
            $this->assertDatabaseCount('journal_details', 0);
        }
        config(['platform.multicurrency_enabled' => false]);
        $this->expectException(\RuntimeException::class);
        PostingService::post($this->header(), $this->lines(), null, $this->fx());
    }

    public function test_txn_balance_is_independently_validated_at_low_exchange_rate(): void
    {
        $lines = $this->lines();
        $lines[0]['amount'] = $lines[1]['amount'] = '0.00';
        $lines[0]['amount_txn'] = '0.01'; $lines[1]['amount_txn'] = '0.02';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mata uang transaksi');
        PostingService::post($this->header(), $lines, null, ['currency' => 'USD', 'rate' => '0.01']);
    }

    public function test_adjustment_rows_are_idr_and_one_cent_tolerance_is_balanced(): void
    {
        $lines = $this->lines(); $lines[0]['amount'] = '15800000.01';
        $lines[] = ['account_code' => '910008', 'position' => 'KREDIT', 'amount' => '0.01', 'fx_adjustment' => true];
        PostingService::post($this->header(), $lines, null, $this->fx());
        $adjustment = DB::table('journal_details')->where('account_code', '910008')->first();
        $this->assertSame('IDR', $adjustment->currency_code);
        $this->assertNull($adjustment->amount_txn);
        $this->assertSame(0, bccomp((string) $adjustment->exchange_rate, '1', 8));
        $this->assertDatabaseCount('journal_details', 3);
    }

    public function test_adjustment_with_transaction_amount_is_rejected(): void
    {
        $lines = $this->lines(); $lines[0]['fx_adjustment'] = true;
        $this->expectException(\RuntimeException::class);
        PostingService::post($this->header(), $lines, null, $this->fx());
    }

    public function test_idr_balance_remains_mandatory_even_when_txn_balance_matches(): void
    {
        $lines = $this->lines(); $lines[0]['amount'] = '15800000.01';
        try {
            PostingService::post($this->header(), $lines, null, $this->fx());
            $this->fail('Jurnal IDR tidak balance harus ditolak.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('tidak balance', $exception->getMessage());
        }
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_detail_insert_failure_rolls_back_header(): void
    {
        $lines = $this->lines(); $lines[0]['missing_column'] = 'invalid'; $lines[1]['missing_column'] = 'invalid';
        try {
            PostingService::post($this->header(), $lines, null, $this->fx());
            $this->fail('Insert invalid harus gagal.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_wrapper_uses_stored_bi_midpoint_and_converts_without_float(): void
    {
        $actor = User::factory()->create();
        // FIX: multicurrency — contoh kurs tengah BI, normalisasi per unit sebelum penyimpanan.
        $mid = Money::div(Money::add('15900', '15700', 8), '2', 8);
        app(ExchangeRateService::class)->save(['currency_code' => 'USD', 'rate_date' => '2026-10-09', 'rate' => $mid, 'type' => 'SPOT', 'source' => 'BI'], null, $actor->id);
        app(FxPostingService::class)->post($this->header(), $this->lines(), 'USD', Carbon::parse('2026-10-09'));
        $this->assertSame(0, bccomp((string) DB::table('journal_details')->first()->amount, '15800000', 2));
    }

    public function test_fx_audit_detects_corrupted_transaction_amount(): void
    {
        PostingService::post($this->header(), $this->lines(), null, $this->fx());
        DB::table('journal_details')->where('position', 'DEBET')->update(['amount_txn' => '999']);
        $this->artisan('jurnal:check-balance', ['start_date' => '2026-10-09', 'end_date' => '2026-10-09', '--fx' => true])
            ->expectsOutputToContain('FX GAGAL')->assertExitCode(1);
    }

    public function test_journal_schema_rollback_rejects_fx_snapshots_before_dropping_columns(): void
    {
        PostingService::post($this->header(), $this->lines(), null, $this->fx());
        $migration = require database_path('migrations/2026_10_11_100001_add_currency_to_journal_tables.php');
        try {
            $migration->down();
            $this->fail('Rollback snapshot FX harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback ditolak', $exception->getMessage());
        }
        $this->assertSame('USD', DB::table('journal_headers')->value('currency_code'));
        $this->assertSame(0, bccomp((string) DB::table('journal_details')->value('amount_txn'), '1000', 4));
        $this->assertDatabaseCount('journal_details', 2);
    }
}
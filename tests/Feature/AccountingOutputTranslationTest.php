<?php

namespace Tests\Feature;

use App\Exports\AccountExport;
use App\Exports\AssetExport;
use App\Exports\JournalExport;
use App\Exports\LedgerExport;
use App\Exports\PaymentPlanExport;
use App\Exports\PaymentPlanKasBankExport;
use App\Exports\PaymentPlanManualWorklistExport;
use App\Models\User;
use App\Support\ReportInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountingOutputTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_headings_follow_each_supported_locale(): void
    {
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $exports = [
                new JournalExport(), new AccountExport(new Request()),
                new AssetExport(), new LedgerExport('111001', '2026-01-01', '2026-12-31'),
                new PaymentPlanExport(null), new PaymentPlanKasBankExport(collect()),
                new PaymentPlanManualWorklistExport(collect()),
            ];
            foreach ($exports as $export) {
                $headings = $export->headings();
                array_walk_recursive($headings, function ($heading) {
                    $this->assertDoesNotMatchRegularExpression('/^(erp|accounting)\./', $heading);
                });
            }
            $this->assertSame(__('erp.date'), (new JournalExport())->headings()[0]);
            $this->assertSame(9, count((new JournalExport())->headings()));
        }
    }

    public function test_journal_form_uses_session_language_for_input_placeholders(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'en'])
            ->get(route('jurnal.create'))
            ->assertOk()
            ->assertSee('Example: INV-SUPPLIER-2026-101')
            ->assertSee('Transaction / Original Document No.')
            ->assertDontSee('placeholder: "Pilih..."', false);
    }

    public function test_printable_report_periods_are_localized_without_changing_period_keys(): void
    {
        app()->setLocale('en');
        $periods = ReportInterval::buildPeriods('bulanan', 2026);
        $this->assertSame('May', $periods[4]['label']);
        $this->assertSame('2026-05', $periods[4]['key']);
        $this->assertSame('Monthly', ReportInterval::label('bulanan'));
        $this->assertSame('May 2026', ReportInterval::rangeLabel('harian', 2026, 5));
    }

    public function test_accounting_catalog_keys_exist_in_all_supported_languages(): void
    {
        $keys = array_keys(require lang_path('id/accounting.php'));
        foreach (['en', 'zh_CN'] as $locale) {
            $catalog = require lang_path($locale.'/accounting.php');
            $this->assertSame($keys, array_keys($catalog));
        }
        foreach ([app_path(), resource_path('views')] as $directory) {
            foreach (\Illuminate\Support\Facades\File::allFiles($directory) as $file) {
                preg_match_all("/__\(\s*['\"]accounting\.([a-z0-9_]+)['\"]\s*[,)]/", file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $key) {
                    $this->assertContains($key, $keys, 'Missing accounting.'.$key);
                }
            }
        }
    }
}
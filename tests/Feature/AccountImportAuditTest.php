<?php

namespace Tests\Feature;

use App\Imports\AccountImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountImportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_round_trip_preserves_master_values_in_every_locale(): void
    {
        Bus::fake();
        $this->freezeTime();
        $accounts = [];
        foreach (['00123', '21999', '61999', '41999', '11999', '31999'] as $index => $code) {
            $accounts[] = \App\Models\Account::create([
                'account_code' => $code, 'account_name' => '=Literal '.$code,
                'coa_type' => 'Cash & Bank', 'normal_balance' => $index % 2 ? 'KREDIT' : 'DEBET',
                'report_pos' => $index % 2 ? 'LABA RUGI' : 'NERACA',
            ])->refresh();
        }
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $bytes = \Maatwebsite\Excel\Facades\Excel::raw(
                new \App\Exports\AccountExport(new \Illuminate\Http\Request()), \Maatwebsite\Excel\Excel::XLSX
            );
            $path = tempnam(sys_get_temp_dir(), 'coa-roundtrip-');
            try {
                file_put_contents($path, $bytes);
                $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $this->assertSame('KODE AKUN', $sheet->getActiveSheet()->getCell('B5')->getValue());
                $this->assertSame('00123', $sheet->getActiveSheet()->getCell('B6')->getValue());
                $this->assertSame('s', $sheet->getActiveSheet()->getCell('C6')->getDataType());
                $sheet->disconnectWorksheets();
                \Maatwebsite\Excel\Facades\Excel::import(new AccountImport(), $path, null, \Maatwebsite\Excel\Excel::XLSX);
                $this->assertDatabaseCount('accounts', 6);
                foreach ($accounts as $account) {
                    $this->assertSame($account->getAttributes(), $account->fresh()->getAttributes());
                }
            } finally {
                unlink($path);
            }
        }
    }

    public function test_export_filters_remain_effective_in_importable_workbook(): void
    {
        Bus::fake();
        foreach (['AUDIT-KEEP', 'AUDIT-SKIP'] as $code) {
            \App\Models\Account::create([
                'account_code' => $code, 'account_name' => $code,
                'coa_type' => 'Cash & Bank', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA',
            ]);
        }
        $export = new \App\Exports\AccountExport(new \Illuminate\Http\Request([
            'search' => 'KEEP', 'coa_type' => 'Cash & Bank', 'report_pos' => 'NERACA',
        ]));
        $path = tempnam(sys_get_temp_dir(), 'coa-filter-');
        try {
            file_put_contents($path, \Maatwebsite\Excel\Facades\Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            \App\Models\Account::where('account_code', 'AUDIT-KEEP')->delete();
            \Maatwebsite\Excel\Facades\Excel::import(new AccountImport(), $path, null, \Maatwebsite\Excel\Excel::XLSX);
            $this->assertDatabaseHas('accounts', ['account_code' => 'AUDIT-KEEP']);
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $this->assertSame(6, $sheet->getActiveSheet()->getHighestDataRow());
            $this->assertSame('AUDIT-KEEP', $sheet->getActiveSheet()->getCell('B6')->getValue());
            $sheet->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_official_balance_labels_are_imported_without_fallback(): void
    {
        Bus::fake();
        $labels = ['DEBET' => 'DEBET', 'DEBIT' => 'DEBET', '借方' => 'DEBET',
            'KREDIT' => 'KREDIT', 'CREDIT' => 'KREDIT', '贷方' => 'KREDIT'];
        foreach ($labels as $label => $expected) {
            $code = 'AUDIT-'.count($this->app['db']->table('accounts')->get());
            (new AccountImport())->collection(collect([[null, $code, 'Test', 'Cash & Bank', $label, 'BALANCE SHEET']]));
            $this->assertDatabaseHas('accounts', ['account_code' => $code, 'normal_balance' => $expected, 'report_pos' => 'NERACA']);
        }
    }

    public function test_unknown_labels_reject_entire_collection_with_excel_row_number(): void
    {
        Bus::fake();
        foreach ([['NOT CREDIT', 'NERACA'], ['DEBET', 'UNKNOWN REPORT']] as [$balance, $report]) {
            try {
                (new AccountImport())->collection(collect([
                    [null, 'AUDIT-VALID', 'Valid', 'Cash & Bank', 'DEBIT', 'NERACA'],
                    [null, 'AUDIT-INVALID', 'Invalid', 'Cash & Bank', $balance, $report],
                ]));
                $this->fail('Invalid labels must be rejected.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('7', implode(' ', $e->errors()['file']));
            }
            $this->assertDatabaseMissing('accounts', ['account_code' => 'AUDIT-VALID']);
            $this->assertDatabaseMissing('accounts', ['account_code' => 'AUDIT-INVALID']);
        }
        Bus::assertNothingDispatched();
    }

    public function test_xlsx_import_starts_at_row_six_and_preserves_existing_accounts_on_invalid_input(): void
    {
        Bus::fake();
        \App\Models\Account::create([
            'account_code' => 'AUDIT-EXISTING', 'account_name' => 'Original',
            'coa_type' => 'Cash & Bank', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA',
        ]);
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet->getActiveSheet()->fromArray([
            [null, 'AUDIT-EXISTING', 'Changed', 'Cash & Bank', ' debit ', 'income statement'],
            [null, 'AUDIT-BAD', 'Invalid', 'Cash & Bank', 'CRED', 'NERACA'],
        ], null, 'A6');
        $path = tempnam(sys_get_temp_dir(), 'coa-audit-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
            try {
                \Maatwebsite\Excel\Facades\Excel::import(new AccountImport(), $path, null, \Maatwebsite\Excel\Excel::XLSX);
                $this->fail('Invalid spreadsheet must be rejected.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('7', implode(' ', $e->errors()['file']));
            }
            $this->assertDatabaseHas('accounts', ['account_code' => 'AUDIT-EXISTING', 'account_name' => 'Original', 'normal_balance' => 'KREDIT']);
            $this->assertDatabaseMissing('accounts', ['account_code' => 'AUDIT-BAD']);
            Bus::assertNothingDispatched();
        } finally {
            unlink($path);
            $sheet->disconnectWorksheets();
        }
    }

    public function test_report_labels_and_whitespace_are_normalized(): void
    {
        Bus::fake();
        foreach (['LABA RUGI', 'PROFIT & LOSS', 'PROFIT AND LOSS', 'INCOME STATEMENT', '损益表', '损益'] as $index => $label) {
            (new AccountImport())->collection(collect([[null, 'REPORT-'.$index, 'Test', 'Expense', ' credit ', ' '.$label.' ']]));
            $this->assertDatabaseHas('accounts', ['account_code' => 'REPORT-'.$index, 'normal_balance' => 'KREDIT', 'report_pos' => 'LABA RUGI']);
        }
    }
}
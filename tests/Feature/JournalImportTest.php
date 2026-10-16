<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class JournalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_month_csv_import_does_not_create_or_wipe_journals(): void
    {
        config(['platform.period_lifecycle_preview_enabled' => true]);
        foreach (['11100', '21100'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $code, 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        }
        DB::table('accounting_periods')->insert(['month' => '2026-09', 'closed' => true]);
        $content = implode("\n", [
            'Tanggal,No Jurnal,No Bukti,Deskripsi,Col5,Col6,Nilai Debet,Nilai Kredit,Akun',
            '2026-09-10,GJ-CLOSED,REF,Test,,,100.00,0.00,11100 - Kas',
            '2026-09-10,GJ-CLOSED,REF,Test,,,0.00,100.00,21100 - Hutang',
        ]);
        $result = app(\App\Services\JournalCsvImportService::class)->import(UploadedFile::fake()->createWithContent('closed.csv', $content));
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('closed', $result['message']);
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_csv_import_creates_header_and_details()
    {
        $this->withoutMiddleware();

        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '21100', 'account_name' => 'Hutang', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // CSV header and two rows forming balanced journal (DEBET and KREDIT)
        $header = 'Tanggal,No Jurnal,No Bukti,Deskripsi,Col5,Col6,Nilai Debet,Nilai Kredit,Akun';
        $row1 = '2026-06-01,GJ-TEST-IMP,INV-001,Desc A,,,100.00,0.00,11100 - Kas';
        $row2 = '2026-06-01,GJ-TEST-IMP,INV-001,Desc A,,,0.00,100.00,21100 - Hutang';

        $content = $header . "\n" . $row1 . "\n" . $row2 . "\n";

        $file = UploadedFile::fake()->createWithContent('jurnal_import.csv', $content);

        $response = $this->post(route('jurnal.import'), [
            'file_excel' => $file
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('journal_headers', [
            'journal_no' => 'GJ-TEST-IMP',
            'source_doc_no' => 'INV-001',
            'journal_type' => 'IMPORT',
        ]);

        $this->assertDatabaseHas('journal_details', [
            'account_code' => '11100',
            'position'     => 'DEBET',
            'amount'       => 100.00
        ]);

        $this->assertDatabaseHas('journal_details', [
            'account_code' => '21100',
            'position'     => 'KREDIT',
            'amount'       => 100.00
        ]);
    }

    public function test_csv_reimport_uses_source_transaction_number_as_its_idempotency_key(): void
    {
        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '21100', 'account_name' => 'Hutang', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $content = implode("\n", [
            'Tanggal,No Jurnal,No Bukti,Deskripsi,Col5,Col6,Nilai Debet,Nilai Kredit,Akun',
            '2026-10-01,GJ-IMPORT-001,INV-FISIK-001,Import ulang,,,100.00,0.00,11100 - Kas',
            '2026-10-01,GJ-IMPORT-001,INV-FISIK-001,Import ulang,,,0.00,100.00,21100 - Hutang',
        ]);

        foreach ([1, 2] as $attempt) {
            $result = app(\App\Services\JournalCsvImportService::class)->import(
                UploadedFile::fake()->createWithContent("jurnal_import_{$attempt}.csv", $content)
            );
            $this->assertSame('success', $result['status'], $result['message']);
        }

        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseHas('journal_headers', [
            'journal_no' => 'GJ-IMPORT-001',
            'source_doc_no' => 'INV-FISIK-001',
            'evidence_number' => 'IMP-20261001-0001',
            'journal_type' => 'IMPORT',
        ]);
    }

    public function test_csv_and_excel_reimports_replace_details_and_preserve_both_numbers(): void
    {
        $this->seedImportAccounts();
        foreach (['csv', 'xlsx', 'csv', 'xlsx'] as $attempt => $extension) {
            $amount = 100 + $attempt;
            $result = $this->importRows($extension, 'GJ-REPEAT', 'REF-'.$attempt, $amount);
            $this->assertSame('success', $result['status'], $result['message']);
            $this->assertDatabaseCount('journal_headers', 1);
            $this->assertDatabaseCount('journal_details', 2);
            $this->assertDatabaseHas('journal_headers', [
                'journal_no' => 'GJ-REPEAT', 'source_doc_no' => 'REF-'.$attempt,
                'notes' => 'Updated description', 'journal_type' => 'IMPORT',
            ]);
            $this->assertDatabaseHas('journal_details', ['position' => 'DEBET', 'amount' => $amount]);
            $this->assertDatabaseHas('journal_details', ['position' => 'KREDIT', 'amount' => $amount]);
        }
    }

    public function test_exported_import_can_be_reimported_without_duplicates(): void
    {
        $this->seedImportAccounts();
        $result = $this->importRows('csv', 'GJ-ROUNDTRIP', 'REF-ROUNDTRIP', 100);
        $this->assertSame('success', $result['status'], $result['message']);
        $content = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\JournalExport(), \Maatwebsite\Excel\Excel::XLSX);
        $result = app(\App\Services\JournalCsvImportService::class)->import(
            UploadedFile::fake()->createWithContent('roundtrip.xlsx', $content)
        );
        $this->assertSame('success', $result['status'], $result['message']);
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
    }

    public function test_legacy_csv_import_is_replaced_using_its_source_number(): void
    {
        $this->seedImportAccounts();
        \App\Models\JournalHeader::create([
            'transaction_date' => '2026-10-01', 'journal_type' => 'IMPORT',
            'source_doc_no' => 'GJ-LEGACY',
        ]);
        $result = $this->importRows('xlsx', 'GJ-LEGACY', 'REF-LEGACY', 100);
        $this->assertSame('success', $result['status'], $result['message']);
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseHas('journal_headers', ['journal_no' => 'GJ-LEGACY', 'source_doc_no' => 'REF-LEGACY']);
    }

    public function test_matching_manual_journals_are_replaced_for_csv_and_excel(): void
    {
        $this->seedImportAccounts();
        foreach (['csv', 'xlsx'] as $extension) {
            foreach (['journal_no', 'evidence_number'] as $identity) {
                $number = 'GJ-MANUAL-'.$extension.'-'.$identity;
                $header = \App\Models\JournalHeader::create([
                    'transaction_date' => $extension === 'csv' ? '2026-10-01' : '2026-10-02',
                    'journal_type' => 'MANUAL',
                    'journal_no' => $identity === 'journal_no' ? $number : null,
                ]);
                if ($identity === 'evidence_number') {
                    $number = $header->evidence_number;
                }
                \App\Models\JournalDetail::create([
                    'journal_id' => $header->getKey(), 'account_code' => '11100',
                    'position' => 'DEBET', 'amount' => 50,
                ]);

                $result = $this->importRows($extension, $number, 'REF-UPDATED', 200);
                $this->assertSame('success', $result['status'], $result['message']);
                $this->assertDatabaseMissing('journal_headers', ['journal_type' => 'MANUAL']);
                $this->assertDatabaseMissing('journal_details', ['amount' => 50]);
                $this->assertDatabaseHas('journal_headers', [
                    'journal_no' => $number, 'journal_type' => 'IMPORT', 'source_doc_no' => 'REF-UPDATED',
                ]);
                $this->assertSame(2, DB::table('journal_details')->where('journal_id',
                    DB::table('journal_headers')->where('journal_no', $number)->value('journal_id')
                )->count());
            }
        }
        $this->assertDatabaseCount('journal_headers', 4);
        $this->assertDatabaseCount('journal_details', 8);
    }

    public function test_matching_automatic_or_transaction_linked_journals_are_rejected_without_changes(): void
    {
        $this->seedImportAccounts();
        foreach (['MANUAL', 'AUTO'] as $type) {
            $header = \App\Models\JournalHeader::create([
                'transaction_date' => '2026-10-01', 'journal_type' => $type, 'journal_no' => 'GJ-'.$type,
                'invoice_id' => $type === 'MANUAL' ? 123 : null,
            ]);
            foreach (['csv', 'xlsx'] as $extension) {
                $result = $this->importRows($extension, 'GJ-'.$type, 'REF-OTHER', 100);
                $this->assertSame('error', $result['status']);
                $this->assertDatabaseHas('journal_headers', ['journal_id' => $header->getKey(), 'journal_type' => $type]);
            }
        }
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_distinct_journal_numbers_with_same_document_are_not_overwritten(): void
    {
        $this->seedImportAccounts();
        foreach (['GJ-FIRST', 'GJ-SECOND'] as $number) {
            $result = $this->importRows('csv', $number, 'SHARED-REF', 100);
            $this->assertSame('success', $result['status'], $result['message']);
        }
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('journal_details', 4);
    }

    public function test_missing_journal_number_falls_back_to_document_number(): void
    {
        $this->seedImportAccounts();
        foreach (['csv', 'xlsx'] as $extension) {
            $result = $this->importRows($extension, '', 'REF-FALLBACK', 100);
            $this->assertSame('success', $result['status'], $result['message']);
        }
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseHas('journal_headers', ['journal_no' => 'REF-FALLBACK', 'source_doc_no' => 'REF-FALLBACK']);
    }

    public function test_invalid_reimport_leaves_previous_journal_intact(): void
    {
        $this->seedImportAccounts();
        $result = $this->importRows('csv', 'GJ-VALID', 'REF-VALID', 100);
        $this->assertSame('success', $result['status'], $result['message']);
        $oldId = DB::table('journal_headers')->value('journal_id');
        foreach (['csv', 'xlsx'] as $extension) {
            $result = $this->importRows($extension, 'GJ-VALID', 'REF-INVALID', 200, 100);
            $this->assertSame('error', $result['status']);
            $this->assertDatabaseHas('journal_headers', ['journal_id' => $oldId, 'source_doc_no' => 'REF-VALID']);
            $this->assertDatabaseCount('journal_headers', 1);
            $this->assertDatabaseCount('journal_details', 2);
        }
    }

    public function test_import_without_either_number_is_rejected(): void
    {
        $this->seedImportAccounts();
        foreach (['csv', 'xlsx'] as $extension) {
            $result = $this->importRows($extension, '', '', 100);
            $this->assertSame('error', $result['status']);
        }
        $this->assertDatabaseCount('journal_headers', 0);
    }

    private function seedImportAccounts(): void
    {
        foreach (['11100', '21100'] as $code) {
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => $code, 'coa_type' => 'ASSET',
                'normal_balance' => 'DEBET', 'report_pos' => 'NERACA',
            ]);
        }
    }

    private function importRows(string $extension, string $number, string $reference, int $amount, ?int $credit = null): array
    {
        $rows = [
            ['01 Oct 2026', $number, $reference, 'Updated description', '', '', $amount.'.00', '0.00', '11100'],
            ['01 Oct 2026', $number, $reference, 'Updated description', '', '', '0.00', ($credit ?? $amount).'.00', '21100'],
        ];
        $headings = (new \App\Exports\JournalExport())->headings();
        if ($extension === 'csv') {
            $stream = fopen('php://temp', 'r+');
            foreach ([$headings, ...$rows] as $row) {
                fputcsv($stream, $row);
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
        } else {
            $export = new class([$headings, ...$rows]) implements \Maatwebsite\Excel\Concerns\FromArray {
                public function __construct(private array $rows) {}
                public function array(): array { return $this->rows; }
            };
            $content = \Maatwebsite\Excel\Facades\Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        }

        return app(\App\Services\JournalCsvImportService::class)->import(
            UploadedFile::fake()->createWithContent('journals.'.$extension, $content)
        );
    }
}
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentImportAmountAuditTest extends TestCase
{
    use RefreshDatabase;

    private function upload(array $rows, string $format = 'id')
    {
        $this->withoutMiddleware();
        if (!DB::table('master_divisi')->exists()) {
            DB::table('master_divisi')->insert(['kode_divisi' => 'FIN', 'nama_divisi' => 'Finance', 'status_aktif' => 1]);
        }
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['NO PP', 'TANGGAL', 'KATEGORI', 'REKENING', 'STATUS', 'PIC', 'DIVISI', 'VENDOR', 'LINK', 'KETERANGAN', 'VA', 'COA', 'QTY', 'SATUAN', 'NOMINAL', 'AKTUAL'], ';');
        foreach ($rows as [$number, $amount, $actual]) {
            fputcsv($stream, [$number, '2026-10-08', 'OPERASIONAL', 'PENDING', 'PENGAJUAN', 'PIC', 'FIN', 'Vendor', '', 'Test', '', '', '1', 'PCS', $amount, $actual], ';');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $this->post(route('payment.import'), ['number_format' => $format,
            'file_csv' => UploadedFile::fake()->createWithContent('payment.csv', $content)]);
    }

    public function test_indonesian_amounts_are_not_silently_zero_or_one(): void
    {
        $this->upload([['ID-A', '1.000,00', '500,50'], ['ID-B', '1.000', '0'], ['ID-C', '1000.50', '']])->assertSessionHas('success');
        foreach (['ID-A' => 1000, 'ID-B' => 1000, 'ID-C' => 1000.50] as $number => $amount) {
            $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => $number, 'nominal' => $amount]);
        }
        $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => 'ID-A', 'nominal_aktual' => 500.50]);
        $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => 'ID-B', 'nominal_aktual' => 0]);
        $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => 'ID-C', 'nominal_aktual' => null]);
    }

    public function test_english_grouping_requires_explicit_english_format(): void
    {
        $this->upload([['EN-A', '1,000.00', '750.25'], ['EN-B', '1,000', '']], 'en')->assertSessionHas('success');
        $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => 'EN-A', 'nominal' => 1000, 'nominal_aktual' => 750.25]);
        $this->assertDatabaseHas('transaksi_payment_plan', ['no_transaksi' => 'EN-B', 'nominal' => 1000]);
    }

    public function test_invalid_values_reject_rows_and_report_actual_record_numbers(): void
    {
        $this->upload([['VALID', '10', ''], ['VALID', '10', ''], ['BAD', '-100', ''],
            ['ZERO', '0', ''], ['TEXT', 'Rp100', ''], ['ACTUAL', '10', 'oops'], ['MIXED', '1,000.00', ''],
            ['HUGE', '999999999999999999999', '']])->assertSessionHas('success', fn ($msg) => str_contains($msg, '[BARIS 4]') && str_contains($msg, 'GAGAL: 6'));
        $this->assertDatabaseCount('transaksi_payment_plan', 1);
        $this->assertDatabaseCount('transaksi_payment_plan_detail', 1);
    }

    public function test_parser_enforces_format_precision_and_quantity_capacity(): void
    {
        foreach ([['1.000', 'id', '1000.00'], ['1,000', 'en', '1000.00'],
            ['1,50', 'id', '1.50'], ['1.50', 'en', '1.50']] as [$value, $format, $expected]) {
            $this->assertSame($expected, \App\Support\PaymentImportAmount::normalize($value, $format));
        }
        foreach ([['1,000', 'id', 13], ['1.000', 'en', 13], ['1.234,567', 'id', 13],
            ['1e3', 'en', 13], ['0', 'id', 8], ['100000000', 'id', 8], ['1 000', 'id', 13]] as [$value, $format, $digits]) {
            try {
                \App\Support\PaymentImportAmount::normalize($value, $format, false, $digits);
                $this->fail('Invalid input must not be normalized: '.$value);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(__('erp.audit_payment_import_amount'), $e->getMessage());
            }
        }
    }
}
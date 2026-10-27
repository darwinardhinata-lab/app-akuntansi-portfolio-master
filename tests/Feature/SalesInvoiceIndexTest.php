<?php

namespace Tests\Feature;

use App\Http\Controllers\SalesInvoiceController;
use App\Support\ReportInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesInvoiceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_aggregates_monthly_sales_without_truncating_amounts(): void
    {
        foreach (['2026-09-01' => '100.25', '2026-09-15' => '200.50', '2026-10-01' => '50.75'] as $date => $amount) {
            $id = DB::table('sales_invoices')->insertGetId([
                'invoice_number' => 'INV-'.$date,
                'transaction_date' => $date,
                'contact_name' => 'Customer Test',
            ]);
            DB::table('sales_invoice_details')->insert([
                'sales_invoice_id' => $id,
                'item_code' => 'SKU-TEST',
                'qty_actual' => 2,
                'amount' => $amount,
            ]);
        }

        $controller = new SalesInvoiceController;
        $view = $controller->index(Request::create('/sales-invoices'));
        $this->assertSame('sales_invoice.index', $view->name());
        $rows = $view->getData()['analyticData']->keyBy('bulan');
        $this->assertCount(2, $rows);
        $this->assertSame('Pusat', $rows['2026-09']->lokasi);
        $this->assertSame('Customer Test', $rows['2026-09']->pelanggan);
        $this->assertSame('SKU-TEST', $rows['2026-09']->sku);
        $this->assertEquals(4, $rows['2026-09']->total_qty);
        $this->assertEquals(300.75, $rows['2026-09']->total_omset);
        $this->assertEquals(50.75, $rows['2026-10']->total_omset);

        $filtered = $controller->index(Request::create('/sales-invoices', 'GET', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]))->getData();
        $this->assertCount(1, $filtered['analyticData']);
        $this->assertSame('2026-10', $filtered['analyticData']->first()->bulan);
        $this->assertSame(1, $filtered['invoices']->total());
    }

    public function test_monthly_expression_uses_mysql_date_format_on_mysql(): void
    {
        $connection = \Mockery::mock(\Illuminate\Database\MySqlConnection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->assertSame("DATE_FORMAT(si.transaction_date, '%Y-%m')", ReportInterval::periodKeyExpr('bulanan', 'si.transaction_date'));
    }

    public function test_index_supports_legacy_schema_without_cancellation_audit_columns(): void
    {
        // Reproduce the reported older schema only on the isolated test database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = require database_path('migrations/2026_10_06_180000_add_invoice_cancellation_audit.php');
        $migration->down();
        $id = DB::table('sales_invoices')->insertGetId([
            'invoice_number' => 'LEGACY-READ', 'transaction_date' => '2026-09-01', 'contact_name' => 'Legacy customer',
        ]);
        DB::table('sales_invoice_details')->insert([
            'sales_invoice_id' => $id, 'item_code' => 'LEGACY-SKU', 'qty_actual' => 2, 'amount' => '123.45',
        ]);
        $this->withoutMiddleware()->get(route('invoice.index'))->assertOk()->assertViewHas('analyticData', function ($rows) {
            return $rows->count() === 1 && (string) $rows->first()->sku === 'LEGACY-SKU'
                && bccomp((string) $rows->first()->total_omset, '123.45', 2) === 0;
        });
    }

    public function test_pivot_excludes_cancelled_invoices_when_audit_schema_exists(): void
    {
        foreach ([null, '2026-09-02 00:00:00'] as $index => $cancelled) {
            $id = DB::table('sales_invoices')->insertGetId([
                'invoice_number' => 'CANCEL-FILTER-'.$index, 'transaction_date' => '2026-09-01',
                'contact_name' => 'Same customer', 'cancelled_at' => $cancelled,
            ]);
            DB::table('sales_invoice_details')->insert([
                'sales_invoice_id' => $id, 'item_code' => 'SAME-SKU', 'qty_actual' => 2, 'amount' => '123.45',
            ]);
        }
        $data = (new SalesInvoiceController())->index(Request::create('/sales-invoices'))->getData();
        $this->assertSame(2, $data['invoices']->total()); // Audit list retains cancelled originals.
        $this->assertCount(1, $data['analyticData']);
        $this->assertEquals(2, $data['analyticData']->first()->total_qty);
        $this->assertSame(0, bccomp((string) $data['analyticData']->first()->total_omset, '123.45', 2));
    }
}
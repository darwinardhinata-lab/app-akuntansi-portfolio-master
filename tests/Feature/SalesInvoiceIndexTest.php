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
}
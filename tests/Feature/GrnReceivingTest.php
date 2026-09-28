<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\GrnScenarios;
use Tests\TestCase;

class GrnReceivingTest extends TestCase
{
    use RefreshDatabase, GrnScenarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGrnScenario();
    }

    public function test_http_receipt_requires_context_and_then_exposes_scoped_history(): void
    {
        $payload = ['receive_date' => '2026-09-25', 'bill_number' => 'BIL-HTTP',
            'request_key' => (string) Str::uuid(), 'items' => [$this->detailId => 40]];
        $this->post(route('po.receive', $this->po->id), $payload)->assertStatus(409);
        $this->assertEmptyReceiving();
        $payload['context_company_id'] = $this->company->id;
        $this->post(route('po.receive', $this->po->id), $payload)->assertRedirect()->assertSessionMissing('error');
        $receipt = DB::table('purchase_receipts')->first();
        $this->get(route('grn.index'))->assertOk()->assertSee('BIL-HTTP');
        $this->get(route('grn.show', $receipt->id))->assertOk()->assertSee($receipt->receipt_number)->assertSee('GRN-SKU');
        $this->get(route('purchase-bills.index'))->assertOk()->assertSee('BIL-HTTP')->assertSee('Supplier');
        $this->member->companies()->detach($this->company->id);
        $this->get(route('grn.show', $receipt->id))->assertStatus(409);
    }

    public function test_bill_delete_route_preserves_posted_grn(): void
    {
        $this->receive(40);
        $id = DB::table('purchase_bills')->value('id');
        $this->delete(route('purchase-bills.destroy', $id))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, DB::table('purchase_bills')->count());
        $this->assertSame(1, DB::table('journal_headers')->count());
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);
    }

    public function test_po_reimport_cannot_replace_received_details(): void
    {
        $this->receive(40);
        $csv = "Tanggal;Purchase Order No.;Item Code;Description;Contact;Harga Satuan;Qty;Diskon;Pajak;Total;Subtotal;Grand Total;Lokasi\n"
            . "2026-09-25;PO-GRN;GRN-SKU;Item;Supplier;10;100;0;0;1000;1000;1000;Pusat\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('po.csv', $csv);
        $this->post(route('po.import'), ['file_csv' => $file])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_order_details', ['id' => $this->detailId, 'qty_received' => 40]);
        $this->assertSame('PARTIAL', $this->po->fresh()->status);
    }

    public function test_tokens_render_on_both_receiving_forms(): void
    {
        $this->get(route('po.index'))->assertOk()->assertSee('request_key')->assertSee('context_company_id');
        $this->get(route('warehouse.inbound.create', ['tab' => 'pembelian']))->assertOk()->assertSee('request_key');
    }

    public function test_approved_mgi_coa_mapping_drives_grn_journal_and_bill_details(): void
    {
        $this->receive(40);

        $bill = DB::table('purchase_bills')->firstOrFail();
        $this->assertSame('211001', $bill->credit_account);
        $this->assertDatabaseHas('purchase_bill_details', [
            'purchase_bill_id' => $bill->id,
            'account_code' => '114001',
            'amount' => 400,
        ]);
        $lines = DB::table('journal_details')->orderBy('position')->get();
        $this->assertCount(2, $lines);
        $this->assertSame(['114001', '211001'], $lines->pluck('account_code')->all());
        $this->assertSame(['DEBET', 'KREDIT'], $lines->pluck('position')->all());
        $this->assertEquals([400.0, 400.0], $lines->pluck('amount')->map(fn ($value) => (float) $value)->all());
    }
}

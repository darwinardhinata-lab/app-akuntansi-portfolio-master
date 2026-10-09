<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\DocumentSequence;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\FinishingStage;
use App\Modules\Manufacturing\Services\BarcodeLabelService;
use Tests\TestCase;

class SequenceAndBarcodeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequence_reserves_numbers_without_requiring_existing_invoice(): void
    {
        $a = DocumentSequence::reserve('sales_invoices', 'invoice_number', 'INV-AUDIT-');
        $b = DocumentSequence::reserve('sales_invoices', 'invoice_number', 'INV-AUDIT-');
        $this->assertSame('INV-AUDIT-0001', $a);
        $this->assertSame('INV-AUDIT-0002', $b);
    }

    public function test_two_so_shipments_use_shared_counter_and_preserve_external_number(): void
    {
        config(['platform.order_company_scope_enabled' => false]);
        \App\Models\Account::create(['account_code' => '411001', 'account_name' => 'Sales', 'normal_balance' => 'KREDIT', 'coa_type' => 'Sales', 'report_pos' => 'LABA RUGI']);
        \App\Models\Product::create(['sku' => 'SEQ-SKU', 'name' => 'Goods', 'stock_quantity' => 10, 'average_cost' => 1]);
        foreach ([null, null, 'EXT-AUDIT'] as $index => $external) {
            $so = \App\Models\SalesOrder::create(['so_number' => 'SO-SEQ-'.$index, 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer', 'status' => 'OPEN']);
            app(\App\Services\SalesOrderService::class)->createInvoiceAndShip($so->id, '2026-10-08',
                [['item_code' => 'SEQ-SKU', 'qty' => 1, 'price' => 10]],
                ['sales_semantic' => 'LOCAL', 'sub_total' => 10, 'grand_total' => 10], true, $external);
        }
        $numbers = \App\Models\SalesInvoice::orderBy('id')->pluck('invoice_number')->all();
        $this->assertStringEndsWith('-0001', $numbers[0]);
        $this->assertStringEndsWith('-0002', $numbers[1]);
        $this->assertSame('EXT-AUDIT', $numbers[2]);
        $this->assertDatabaseCount('document_sequence_counters', 1);
    }

    public function test_counter_reconciles_numeric_suffix_beyond_padding(): void
    {
        foreach (['INV-HIST-9999', 'INV-HIST-10000'] as $number) {
            \App\Models\SalesInvoice::create(['invoice_number' => $number, 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer']);
        }
        $this->assertSame('INV-HIST-10001', DocumentSequence::reserve('sales_invoices', 'invoice_number', 'INV-HIST-'));
    }

    public function test_sequence_reservation_rolls_back_with_failed_shipment_transaction(): void
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        $this->assertSame('INV-ROLLBACK-0001', DocumentSequence::reserve('sales_invoices', 'invoice_number', 'INV-ROLLBACK-'));
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertDatabaseCount('document_sequence_counters', 0);
        $this->assertSame('INV-ROLLBACK-0001', DocumentSequence::reserve('sales_invoices', 'invoice_number', 'INV-ROLLBACK-'));
    }

    public function test_planned_quantity_without_packing_does_not_allow_labels(): void
    {
        $wo = WorkOrder::create(['spk_number' => 'SPK-NO-PACK', 'order_date' => '2026-10-08', 'garment_name' => 'Test', 'planned_qty' => 100, 'status' => 'FINISHING']);
        try {
            app(BarcodeLabelService::class)->generate($wo->id, [['size' => 'M', 'qty' => 1, 'mrp' => 100]], 'NO-PACK');
            $this->fail('Planning is not packing output.');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertDatabaseCount('mfg_barcode_labels', 0);
    }

    public function test_label_batch_retry_is_idempotent_and_output_is_bounded(): void
    {
        $wo = WorkOrder::create(['spk_number' => 'SPK-AUDIT', 'order_date' => '2026-10-08', 'garment_name' => 'Test', 'planned_qty' => 100, 'status' => 'FINISHING']);
        $fabric = \App\Modules\Manufacturing\Models\Fabric::create(['fabric_code' => 'F-AUDIT', 'fabric_type' => 'Cotton', 'state' => 'FINISHED', 'unit' => 'KGS']);
        $cut = \Illuminate\Support\Facades\DB::table('mfg_cutting_orders')->insertGetId(['cutting_order_number' => 'CO-AUDIT', 'order_date' => '2026-10-08', 'work_order_id' => $wo->id, 'fabric_id' => $fabric->id, 'fabric_qty_issued' => 1, 'planned_pieces' => 3]);
        $sew = \Illuminate\Support\Facades\DB::table('mfg_stitching_orders')->insertGetId(['stitching_order_number' => 'SEW-AUDIT', 'order_date' => '2026-10-08', 'cutting_order_id' => $cut, 'work_order_id' => $wo->id, 'pieces_issued' => 3]);
        FinishingStage::create(['stitching_order_id' => $sew, 'work_order_id' => $wo->id, 'stage' => 'PACKING', 'stage_date' => '2026-10-08', 'pieces_in' => 3, 'pieces_ok' => 3, 'pieces_rejected' => 0]);
        $service = app(BarcodeLabelService::class);
        $rows = [['size' => 'M', 'qty' => 2, 'mrp' => 100]];
        $labels = $service->generate($wo->id, $rows, 'BATCH-A');
        $retry = $service->generate($wo->id, $rows, 'BATCH-A');
        $this->assertSame(array_map(fn ($l) => $l->id, $labels), array_map(fn ($l) => $l->id, $retry));
        foreach ([['BATCH-A', 1], ['BATCH-B', 2], ['BATCH-C', 1001]] as [$batch, $qty]) {
            try {
                $service->generate($wo->id, [['size' => 'M', 'qty' => $qty, 'mrp' => 100]], $batch);
                $this->fail('Invalid batch must fail.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertDatabaseCount('mfg_barcode_labels', 2);
        foreach ([
            [['size' => 'M', 'qty' => 1.5, 'mrp' => 100]],
            [['size' => 'M', 'qty' => 1, 'mrp' => -1]],
            [['size' => 'M', 'qty' => 1, 'mrp' => 100], ['size' => 'M', 'qty' => 1, 'mrp' => 100]],
            [['size' => 'M', 'qty' => 2, 'mrp' => 101]],
        ] as $invalidRows) {
            try {
                $service->generate($wo->id, $invalidRows, 'BATCH-A');
                $this->fail('Invalid or changed retry must fail.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertDatabaseCount('mfg_barcode_labels', 2);
    }
}
<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\User;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\KnitOrder;
use App\Modules\Manufacturing\Models\ProcessingOrder;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\KnitOrderService;
use App\Modules\Manufacturing\Services\MaklunIssueReversalService;
use App\Modules\Manufacturing\Services\MaklunReceiptService;
use App\Modules\Manufacturing\Services\ProcessingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaklunReceiptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_completion_uses_snapshot_and_reversal_preserves_originals(): void
    {
        config(['platform.maklun_issue_enabled' => true, 'platform.maklun_receipt_enabled' => true]);
        $operator = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($operator);
        config(['platform.maklun_reversal_user_ids' => [$operator->id]]);
        foreach (['114003' => 'DEBET', '114008' => 'DEBET', '212001' => 'KREDIT'] as $code => $normal) {
            Account::create(['account_code' => (string) $code, 'account_name' => 'Test', 'coa_type' => 'Test', 'normal_balance' => $normal, 'report_pos' => 'NERACA']);
        }
        $supplier = Supplier::create(['supplier_code' => 'MAK', 'supplier_name' => 'Maklun', 'supplier_type' => 'OTHER']);
        foreach ([true, false] as $knitting) {
            $suffix = $knitting ? 'K' : 'P';
            $target = Fabric::create(['fabric_code' => 'TARGET-'.$suffix, 'fabric_type' => 'Target', 'state' => $knitting ? 'GREY' : 'FINISHED', 'unit' => 'KGS', 'inventory_account_code' => '114008', 'stock_quantity' => 0, 'average_cost' => 0]);
            if ($knitting) {
                $source = Yarn::create(['yarn_code' => 'YARN', 'yarn_type' => 'Yarn', 'unit' => 'KGS', 'inventory_account_code' => '114003', 'stock_quantity' => 10, 'average_cost' => 10]);
                $order = KnitOrder::create(['knit_order_number' => 'KO', 'order_date' => '2026-10-02', 'supplier_id' => $supplier->id, 'fabric_id' => $target->id, 'planned_qty_kg' => 5, 'status' => 'OPEN']);
                $issue = app(KnitOrderService::class)->issueYarn($order->id, '2026-10-02', [['yarn_id' => $source->id, 'qty_issued' => 5]])[0];
            } else {
                $source = Fabric::create(['fabric_code' => 'SOURCE', 'fabric_type' => 'Source', 'state' => 'GREY', 'unit' => 'KGS', 'inventory_account_code' => '114003', 'stock_quantity' => 10, 'average_cost' => 10]);
                $order = ProcessingOrder::create(['order_number' => 'PRC', 'order_date' => '2026-10-02', 'supplier_id' => $supplier->id, 'process_type' => 'DYEING', 'status' => 'OPEN']);
                $issue = app(ProcessingOrderService::class)->issueFabric($order->id, $source->id, 5, '2026-10-02');
            }
            $this->assertSame('114003', $issue->source_account_code);
            $source->update(['inventory_account_code' => '114008']);
            $data = ['receipt_date' => '2026-10-02', 'qty_received' => 5, 'qty_rejected' => 0,
                'full_completion' => 1, 'liability_account_code' => '212001', 'finished_fabric_id' => $target->id,
                'knitting_cost_amount' => 20, 'process_cost_amount' => 20];
            try {
                app(MaklunReceiptService::class)->receive($knitting, $order->id, array_replace($data, ['qty_rejected' => 1]));
                $this->fail('Reject quantities accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('qty_rejected', $e->errors());
            }
            $this->assertEquals(0, $target->fresh()->stock_quantity);
            $receipt = app(MaklunReceiptService::class)->receive($knitting, $order->id, $data);
            $this->assertEquals(5, $target->fresh()->stock_quantity);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->journal_id, 'account_code' => '114003', 'position' => 'KREDIT', 'amount' => 50]);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->journal_id, 'account_code' => '114008', 'position' => 'DEBET', 'amount' => 70]);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->journal_id, 'account_code' => '212001', 'position' => 'KREDIT', 'amount' => 20]);
            foreach (['header_update', 'detail_delete', 'detail_insert'] as $action) {
                try {
                    if ($action === 'header_update') {
                        JournalHeader::where('journal_id', $receipt->journal_id)->update(['notes' => 'tampered']);
                    } elseif ($action === 'detail_delete') {
                        JournalDetail::where('journal_id', $receipt->journal_id)->delete();
                    } else {
                        JournalDetail::insert([['journal_id' => $receipt->journal_id, 'account_code' => '114003', 'position' => 'DEBET', 'amount' => 1]]);
                    }
                    $this->fail('Sealed journal mutation accepted');
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('sealed', $e->getMessage());
                }
            }
            app(MaklunReceiptService::class)->reverse($knitting, $receipt->id, 'Koreksi receipt disahkan Finance');
            $this->assertEquals(0, $target->fresh()->stock_quantity);
            $this->assertSame('REVERSED', $receipt->fresh()->posting_status);
            $this->assertDatabaseHas('journal_headers', ['journal_id' => $receipt->journal_id]);
            $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => $receipt->receipt_number, 'type' => 'IN']);
            $source->update(['inventory_account_code' => '114003']);
            app(MaklunIssueReversalService::class)->reverse($knitting, $issue->id, 'Bahan dikembalikan penuh dari maklun');
            $this->assertEquals(10, $source->fresh()->stock_quantity);
            $this->assertNotNull($issue->fresh()->reversed_at);
            $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => $issue->issue_number, 'type' => 'OUT']);
            $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => 'REV-'.$issue->issue_number, 'type' => 'IN']);
            try {
                app(MaklunIssueReversalService::class)->reverse($knitting, $issue->id, 'Bahan dikembalikan penuh dari maklun');
                $this->fail('Issue reversed twice');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('sudah dibalik', $e->getMessage());
            }
            try {
                app(MaklunReceiptService::class)->reverse($knitting, $receipt->id, 'Koreksi receipt disahkan Finance');
                $this->fail('Repeated reversal accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('snapshot', $e->getMessage());
            }
        }
        $this->assertSame(4, DB::table('journal_headers')->count());
    }
}

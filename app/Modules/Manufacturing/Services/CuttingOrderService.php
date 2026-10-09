<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\CuttingCheck;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use App\Modules\Platform\Support\CompanyCoaResolver;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Tahap Cutting (Anthrilo: cutting_orders + cutting_checks).
 *
 * INI ADALAH TITIK AWAL WIP YANG SEBENARNYA (lihat catatan di KnitOrderService):
 * begitu kain finished dipotong sesuai pola utk 1 SPK/style tertentu, nilainya
 * tidak lagi bisa dialihkan ke SPK lain, sehingga wajib direklas dari
 * Persediaan Bahan Baku Kain (fungible) ke WIP Produksi (tied ke SPK).
 *
 * 1. create() -> JURNAL #4:
 *      Debit  Barang dalam proses (mapping MGI: 114002)
 *      Kredit Bahan baku (mapping MGI: 114003)
 * 2. recordCheck() -> JURNAL #4b (HANYA jika ada wastage, fabric_wastage_kg > 0):
 *      Debit  Penyesuaian Persediaan untuk wastage/reject tidak bernilai (510004)
 *      Debit  Scrap-Afal untuk scrap bernilai (114005)
 *      Kredit Barang dalam proses (114002)
 *    Wastage TIDAK dikapitalisasi ke HPP barang jadi — ini kerugian operasional
 *    murni (efisiensi marker/proses potong), sesuai praktik costing garmen standar.
 */
class CuttingOrderService
{
    /**
     * @param array $data ['order_date','fabric_qty_issued','planned_pieces','size_breakdown',
     *                      'marker_efficiency','remarks','created_by']
     */
    public function create(int $workOrderId, int $fabricId, array $data): CuttingOrder
    {
        return DB::transaction(function () use ($workOrderId, $fabricId, $data) {
            \App\Support\AccountingPeriodGuard::source([$data['order_date'] ?? null]);
            $company = app(OperationalCompany::class)->company();
            $coa = app(CompanyCoaResolver::class);
            $wipAccount = $coa->account($company, 'wip_inventory');
            $rawMaterialAccount = $coa->account($company, 'raw_material_inventory');
            $workOrder = WorkOrder::lockForUpdate()->findOrFail($workOrderId);
            if (! $workOrder->line_id) {
                throw new Exception("SPK '{$workOrder->spk_number}' belum memiliki Line Produksi. Tetapkan Line Produksi sebelum Line Preparation/Cutting.");
            }
            $productionLine = ProductionLine::lockForUpdate()->findOrFail($workOrder->line_id);
            if (! $productionLine->is_active) {
                throw new Exception("Line Produksi '{$productionLine->line_code}' tidak aktif. Aktifkan atau tetapkan line aktif lain sebelum Cutting.");
            }
            $fabric = Fabric::lockForUpdate()->findOrFail($fabricId);

            $cuttingOrderNumber = DocumentSequence::generateSecure(
                'mfg_cutting_orders', 'cutting_order_number', 'CO-' . now()->format('Ymd') . '-'
            );

            $existing = JournalHeader::where('evidence_number', $cuttingOrderNumber)
                ->where('transaction_type', 'Cutting Order (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor CO '{$cuttingOrderNumber}' sudah memiliki jurnal.");
            }

            $qtyIssued = (float) $data['fabric_qty_issued'];

            $result = MaterialCostHelper::issueStock(
                $fabric, 'FABRIC', $qtyIssued, $data['order_date'], $cuttingOrderNumber,
                "Cutting Order: {$cuttingOrderNumber} - {$workOrder->garment_name}"
            );

            $now = now();
            $journal = JournalHeader::create([
                'transaction_date' => $data['order_date'],
                'evidence_number'  => $cuttingOrderNumber,
                'description'      => "Cutting Order {$cuttingOrderNumber} utk SPK {$workOrder->spk_number} - {$workOrder->garment_name}",
                'transaction_type' => 'Cutting Order (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $result['total_cost'], 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => $rawMaterialAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $result['total_cost'], 'created_at' => $now, 'updated_at' => $now],
            ];
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception('Jurnal Cutting Order tidak balance (Debet != Kredit).');
            }
            JournalDetail::insert($journalRows);

            $cuttingOrder = CuttingOrder::create([
                'cutting_order_number' => $cuttingOrderNumber,
                'order_date'           => $data['order_date'],
                'work_order_id'        => $workOrder->id,
                'line_id'              => $productionLine->id,
                'fabric_id'            => $fabric->id,
                'fabric_qty_issued'    => $qtyIssued,
                'fabric_unit_cost'     => $result['unit_cost'],
                'fabric_total_cost'    => $result['total_cost'],
                'planned_pieces'       => $data['planned_pieces'],
                'size_breakdown'       => $data['size_breakdown'] ?? null,
                'marker_efficiency'    => $data['marker_efficiency'] ?? null,
                'status'               => 'OPEN',
                'remarks'              => $data['remarks'] ?? null,
                'created_by'           => $data['created_by'] ?? null,
            ]);

            // Mulai akumulasi biaya WIP SPK dari sini (lihat dokumentasi kelas di atas).
            WorkOrderService::accumulateCost($workOrder->id, materialCost: $result['total_cost'], processCost: 0);

            if ($workOrder->status === 'DRAFT') {
                $workOrder->status = 'CUTTING';
                $workOrder->save();
            }

            return $cuttingOrder;
        });
    }

    /**
     * @param array $data ['check_date','pieces_cut','pieces_ok','pieces_rejected',
     *                      'fabric_used_kg','fabric_wastage_kg','scrap_kg','scrap_unit_value','size_breakdown_actual',
     *                      'checked_by','remarks']
     */
    public function recordCheck(int $cuttingOrderId, array $data): CuttingCheck
    {
        return DB::transaction(function () use ($cuttingOrderId, $data) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($cuttingOrderId);
            if ($cuttingOrder->status !== 'OPEN' || CuttingCheck::where('cutting_order_id', $cuttingOrder->id)->whereNull('voided_at')->exists()) {
                throw new Exception("QC Cutting untuk '{$cuttingOrder->cutting_order_number}' sudah diposting atau statusnya tidak dapat diperiksa ulang.");
            }

            $company = app(OperationalCompany::class)->company();
            $coa = app(CompanyCoaResolver::class);
            $wipAccount = $coa->account($company, 'wip_inventory');
            $scrapAccount = $coa->account($company, 'scrap_inventory');
            $wastageExpenseAccount = $coa->account($company, 'inventory_adjustment');

            $wastageKg = (float) ($data['fabric_wastage_kg'] ?? 0);
            $scrapKg = (float) ($data['scrap_kg'] ?? 0);
            $scrapUnitValue = (float) ($data['scrap_unit_value'] ?? 0);
            if ($wastageKg < 0 || $scrapKg < 0 || $scrapUnitValue < 0
                || $wastageKg + $scrapKg > (float) $cuttingOrder->fabric_qty_issued) {
                throw new Exception('Kuantitas wastage dan scrap tidak valid atau melebihi kain yang dikeluarkan untuk Cutting Order.');
            }
            if ($scrapKg > 0 && $scrapUnitValue <= 0) {
                throw new Exception('Scrap bernilai harus memiliki nilai per kg yang positif.');
            }

            $wastageCostAmount = round($wastageKg * (float) $cuttingOrder->fabric_unit_cost, 2);
            $scrapValueAmount = round($scrapKg * $scrapUnitValue, 2);
            $now = now();
            $journalId = null;

            if ($wastageCostAmount > 0 || $scrapValueAmount > 0) {
                $journal = JournalHeader::create([
                    'transaction_date' => $data['check_date'],
                    'evidence_number'  => $cuttingOrder->cutting_order_number . '-QC',
                    'description'      => "QC Cutting {$cuttingOrder->cutting_order_number}: wastage {$wastageKg} kg; scrap {$scrapKg} kg",
                    'transaction_type' => 'Cutting QC Scrap/Wastage (MFG)',
                ]);

                $journalRows = [];
                if ($wastageCostAmount > 0) {
                    $journalRows[] = ['journal_id' => $journal->getKey(), 'account_code' => $wastageExpenseAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $wastageCostAmount, 'created_at' => $now, 'updated_at' => $now];
                }
                if ($scrapValueAmount > 0) {
                    $journalRows[] = ['journal_id' => $journal->getKey(), 'account_code' => $scrapAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $scrapValueAmount, 'created_at' => $now, 'updated_at' => $now];
                }
                $journalRows[] = ['journal_id' => $journal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $wastageCostAmount + $scrapValueAmount, 'created_at' => $now, 'updated_at' => $now];
                if (!JournalBalanceValidator::isBalanced($journalRows)) {
                    throw new Exception('Jurnal QC Cutting Scrap/Wastage tidak balance (Debet != Kredit).');
                }
                JournalDetail::insert($journalRows);
                $journalId = $journal->getKey();

                if ($cuttingOrder->work_order_id) {
                    WorkOrderService::accumulateCost($cuttingOrder->work_order_id, materialCost: 0, processCost: 0, wastageCost: $wastageCostAmount + $scrapValueAmount);
                }
            }

            $check = CuttingCheck::create([
                'cutting_order_id'      => $cuttingOrder->id,
                'check_date'            => $data['check_date'],
                'pieces_cut'            => $data['pieces_cut'],
                'pieces_ok'             => $data['pieces_ok'],
                'pieces_rejected'       => $data['pieces_rejected'] ?? 0,
                'fabric_used_kg'        => $data['fabric_used_kg'] ?? null,
                'fabric_wastage_kg'     => $wastageKg,
                'scrap_kg'              => $scrapKg,
                'scrap_unit_value'      => $scrapUnitValue,
                'scrap_value_amount'    => $scrapValueAmount,
                'wastage_cost_amount'   => $wastageCostAmount,
                'journal_id'            => $journalId,
                'size_breakdown_actual' => $data['size_breakdown_actual'] ?? null,
                'checked_by'            => $data['checked_by'] ?? null,
                'remarks'               => $data['remarks'] ?? null,
            ]);

            $cuttingOrder->status = 'CHECKED';
            $cuttingOrder->save();

            return $check;
        });
    }

    /**
     * STAGE 7 — Void Cutting Order: membalik Jurnal #4 + kartu stok kain finished,
     * kurangi kembali akumulasi biaya WIP SPK. Hanya boleh jika BELUM ada QC
     * (status OPEN) dan BELUM ada Stitching Order turunannya.
     */
    public function void(int $cuttingOrderId): bool
    {
        return DB::transaction(function () use ($cuttingOrderId) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($cuttingOrderId);

            if ($cuttingOrder->status !== 'OPEN') {
                throw new Exception(
                    "Tidak bisa void: Cutting Order '{$cuttingOrder->cutting_order_number}' berstatus {$cuttingOrder->status}. " .
                    "Hanya bisa void selama belum ada QC/Cutting Check."
                );
            }
            if (StitchingOrder::where('cutting_order_id', $cuttingOrder->id)->exists()) {
                throw new Exception("Tidak bisa void: sudah ada Stitching Order turunan dari Cutting Order ini.");
            }

            $fabric = Fabric::lockForUpdate()->findOrFail($cuttingOrder->fabric_id);
            $fabric->stock_quantity = (float) $fabric->stock_quantity + (float) $cuttingOrder->fabric_qty_issued;
            $fabric->save();

            MaterialLedger::where('evidence_number', $cuttingOrder->cutting_order_number)->delete();

            JournalDetail::whereIn('journal_id', function ($q) use ($cuttingOrder) {
                $q->select('journal_id')->from('journal_headers')
                    ->where('evidence_number', $cuttingOrder->cutting_order_number)
                    ->where('transaction_type', 'Cutting Order (MFG)');
            })->delete();
            JournalHeader::where('evidence_number', $cuttingOrder->cutting_order_number)
                ->where('transaction_type', 'Cutting Order (MFG)')
                ->delete();

            $workOrderId = $cuttingOrder->work_order_id;
            WorkOrderService::accumulateCost($workOrderId, materialCost: -1 * (float) $cuttingOrder->fabric_total_cost, processCost: 0);

            $cuttingOrder->delete();

            if (CuttingOrder::where('work_order_id', $workOrderId)->doesntExist()) {
                $workOrder = WorkOrder::lockForUpdate()->find($workOrderId);
                if ($workOrder && $workOrder->status === 'CUTTING') {
                    $workOrder->status = 'DRAFT';
                    $workOrder->save();
                }
            }

            return true;
        });
    }

    /**
     * Void QC Cutting by posting an append-only reversal; QC source and its journal are retained.
     */
    public function voidCheck(int $cuttingCheckId): bool
    {
        return DB::transaction(function () use ($cuttingCheckId) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $check = CuttingCheck::lockForUpdate()->findOrFail($cuttingCheckId);
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($check->cutting_order_id);

            if ($check->voided_at) {
                throw new Exception('QC Cutting ini sudah dibatalkan sebelumnya.');
            }

            if (StitchingOrder::where('cutting_order_id', $cuttingOrder->id)->exists()) {
                throw new Exception("Tidak bisa void: sudah ada Stitching Order turunan dari Cutting Order ini.");
            }

            $reversalAmount = (float) $check->wastage_cost_amount + (float) $check->scrap_value_amount;
            if ($reversalAmount > 0) {
                $company = app(OperationalCompany::class)->company();
                $coa = app(CompanyCoaResolver::class);
                $wipAccount = $coa->account($company, 'wip_inventory');
                $scrapAccount = $coa->account($company, 'scrap_inventory');
                $wastageExpenseAccount = $coa->account($company, 'inventory_adjustment');
                $now = now();
                $reversal = JournalHeader::create([
                    'transaction_date' => $now->toDateString(),
                    'evidence_number' => $cuttingOrder->cutting_order_number . '-QC-VOID-' . $check->id,
                    'description' => "Pembalikan QC Cutting {$cuttingOrder->cutting_order_number}; referensi QC #{$check->id}",
                    'transaction_type' => 'Cutting QC Scrap/Wastage Reversal (MFG)',
                ]);
                $rows = [];
                if ((float) $check->wastage_cost_amount > 0) {
                    $rows[] = ['journal_id' => $reversal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $check->wastage_cost_amount, 'created_at' => $now, 'updated_at' => $now];
                    $rows[] = ['journal_id' => $reversal->getKey(), 'account_code' => $wastageExpenseAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $check->wastage_cost_amount, 'created_at' => $now, 'updated_at' => $now];
                }
                if ((float) $check->scrap_value_amount > 0) {
                    $rows[] = ['journal_id' => $reversal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $check->scrap_value_amount, 'created_at' => $now, 'updated_at' => $now];
                    $rows[] = ['journal_id' => $reversal->getKey(), 'account_code' => $scrapAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $check->scrap_value_amount, 'created_at' => $now, 'updated_at' => $now];
                }
                if (! JournalBalanceValidator::isBalanced($rows)) {
                    throw new Exception('Jurnal pembalikan QC Cutting tidak balance (Debet != Kredit).');
                }
                JournalDetail::insert($rows);

                if ($cuttingOrder->work_order_id) {
                    WorkOrderService::accumulateCost($cuttingOrder->work_order_id, materialCost: 0, processCost: 0, wastageCost: -1 * $reversalAmount);
                }

                $check->reversal_journal_id = $reversal->getKey();
            }

            $check->voided_at = now();
            $check->save();

            $cuttingOrder->status = 'OPEN';
            $cuttingOrder->save();

            return true;
        });
    }
}

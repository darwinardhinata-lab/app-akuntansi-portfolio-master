<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\CuttingCheck;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
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
 *      Debit  WIP Produksi (config('coa.wip_produksi'))
 *      Kredit Persediaan Bahan Baku Kain (config('coa.persediaan_bahan_baku_kain'))
 * 2. recordCheck() -> JURNAL #4b (HANYA jika ada wastage, fabric_wastage_kg > 0):
 *      Debit  Kerugian Wastage Produksi (config('coa.kerugian_wastage_produksi'))
 *      Kredit WIP Produksi
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
            $workOrder = WorkOrder::lockForUpdate()->findOrFail($workOrderId);
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
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.wip_produksi'), 'helper_code' => null, 'position' => 'DEBET', 'amount' => $result['total_cost'], 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.persediaan_bahan_baku_kain'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $result['total_cost'], 'created_at' => $now, 'updated_at' => $now],
            ];
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception('Jurnal Cutting Order tidak balance (Debet != Kredit).');
            }
            JournalDetail::insert($journalRows);

            $cuttingOrder = CuttingOrder::create([
                'cutting_order_number' => $cuttingOrderNumber,
                'order_date'           => $data['order_date'],
                'work_order_id'        => $workOrder->id,
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
     *                      'fabric_used_kg','fabric_wastage_kg','size_breakdown_actual',
     *                      'checked_by','remarks']
     */
    public function recordCheck(int $cuttingOrderId, array $data): CuttingCheck
    {
        return DB::transaction(function () use ($cuttingOrderId, $data) {
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($cuttingOrderId);

            $wastageKg = (float) ($data['fabric_wastage_kg'] ?? 0);
            $wastageCostAmount = 0;

            if ($wastageKg > 0) {
                $wastageCostAmount = $wastageKg * (float) $cuttingOrder->fabric_unit_cost;

                $now = now();
                $journal = JournalHeader::create([
                    'transaction_date' => $data['check_date'],
                    'evidence_number'  => $cuttingOrder->cutting_order_number . '-WASTE',
                    'description'      => "Wastage Cutting Order {$cuttingOrder->cutting_order_number}: {$wastageKg} kg",
                    'transaction_type' => 'Cutting Wastage (MFG)',
                ]);

                $journalRows = [
                    ['journal_id' => $journal->getKey(), 'account_code' => config('coa.kerugian_wastage_produksi'), 'helper_code' => null, 'position' => 'DEBET', 'amount' => $wastageCostAmount, 'created_at' => $now, 'updated_at' => $now],
                    ['journal_id' => $journal->getKey(), 'account_code' => config('coa.wip_produksi'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $wastageCostAmount, 'created_at' => $now, 'updated_at' => $now],
                ];
                if (!JournalBalanceValidator::isBalanced($journalRows)) {
                    throw new Exception('Jurnal Wastage Cutting tidak balance (Debet != Kredit).');
                }
                JournalDetail::insert($journalRows);

                if ($cuttingOrder->work_order_id) {
                    WorkOrderService::accumulateCost($cuttingOrder->work_order_id, materialCost: 0, processCost: 0, wastageCost: $wastageCostAmount);
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
                'wastage_cost_amount'   => $wastageCostAmount,
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
     * STAGE 7 — Void Cutting Check (QC + wastage): membalik Jurnal #4b (jika ada
     * wastage) dan mengembalikan status Cutting Order ke OPEN. Ditolak jika sudah
     * ada Stitching Order turunannya.
     */
    public function voidCheck(int $cuttingCheckId): bool
    {
        return DB::transaction(function () use ($cuttingCheckId) {
            $check = CuttingCheck::lockForUpdate()->findOrFail($cuttingCheckId);
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($check->cutting_order_id);

            if (StitchingOrder::where('cutting_order_id', $cuttingOrder->id)->exists()) {
                throw new Exception("Tidak bisa void: sudah ada Stitching Order turunan dari Cutting Order ini.");
            }

            if ((float) $check->wastage_cost_amount > 0) {
                $evidence = $cuttingOrder->cutting_order_number . '-WASTE';
                JournalDetail::whereIn('journal_id', function ($q) use ($evidence) {
                    $q->select('journal_id')->from('journal_headers')
                        ->where('evidence_number', $evidence)
                        ->where('transaction_type', 'Cutting Wastage (MFG)');
                })->delete();
                JournalHeader::where('evidence_number', $evidence)
                    ->where('transaction_type', 'Cutting Wastage (MFG)')
                    ->delete();

                if ($cuttingOrder->work_order_id) {
                    WorkOrderService::accumulateCost($cuttingOrder->work_order_id, materialCost: 0, processCost: 0, wastageCost: -1 * (float) $check->wastage_cost_amount);
                }
            }

            $check->delete();

            $cuttingOrder->status = 'OPEN';
            $cuttingOrder->save();

            return true;
        });
    }
}

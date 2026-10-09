<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\BarcodeLabel;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\FinishingStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cetak label barang jadi (Anthrilo: barcode_labels). TIDAK ada jurnal —
 * murni identitas fisik produk (barcode/MRP/batch) untuk siap jual,
 * dibuat SEBELUM atau SESUDAH WorkOrderService::complete() (urutan bebas,
 * tidak saling bergantung secara akuntansi).
 */
class BarcodeLabelService
{
    /**
     * @param array $sizes list of ['size','qty','mrp']
     */
    public function generate(int $workOrderId, array $sizes, string $batchNumber, ?int $finishingStageId = null): array
    {
        $batchNumber = trim($batchNumber);
        if ($batchNumber === '' || mb_strlen($batchNumber) > 50 || !$sizes || count($sizes) > 100) {
            throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
        }
        $normalized = [];
        $total = 0;
        foreach ($sizes as $row) {
            $size = trim((string) ($row['size'] ?? ''));
            $qty = $row['qty'] ?? null;
            $mrp = $row['mrp'] ?? null;
            if ($size === '' || mb_strlen($size) > 20 || isset($normalized[$size])
                || !is_numeric($qty) || (float) $qty != (int) $qty || (int) $qty < 1 || (float) $qty > 1000
                || !is_numeric($mrp) || !is_finite((float) $mrp) || (float) $mrp < 0 || (float) $mrp > 9999999999999999.99) {
                throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
            }
            $normalized[$size] = ['size' => $size, 'qty' => (int) $qty, 'mrp' => round((float) $mrp, 2)];
            $total += (int) $qty;
        }
        if ($total > 1000) throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
        ksort($normalized);
        $sizes = array_values($normalized);
        // Dibungkus transaction: barcode punya unique constraint di DB (lihat
        // migration mfg_barcode_labels). Tanpa transaction, collision Str::random(6)
        // di tengah loop akan menyisakan sebagian label sudah ter-insert (partial
        // batch) sementara sisanya gagal — membuat batch_number tidak konsisten.
        return DB::transaction(function () use ($workOrderId, $sizes, $batchNumber, $finishingStageId, $total) {
            $workOrder = WorkOrder::lockForUpdate()->findOrFail($workOrderId);
            $existing = BarcodeLabel::where('work_order_id', $workOrderId)->where('batch_number', $batchNumber)->orderBy('id')->get();
            if ($existing->isNotEmpty()) {
                $summary = [];
                foreach ($existing->groupBy('size') as $size => $group) {
                    if ($group->contains(fn ($l) => $l->finishing_stage_id != $finishingStageId || round((float) $l->mrp, 2) !== round((float) $group->first()->mrp, 2))) {
                        throw new \InvalidArgumentException(__('erp.audit_barcode_conflict'));
                    }
                    $summary[$size] = ['size' => (string) $size, 'qty' => $group->count(), 'mrp' => round((float) $group->first()->mrp, 2)];
                }
                ksort($summary);
                if (array_values($summary) !== $sizes) throw new \InvalidArgumentException(__('erp.audit_barcode_conflict'));
                return $existing->all();
            }
            $stages = FinishingStage::where('work_order_id', $workOrderId)->where('stage', 'PACKING')->lockForUpdate()->get();
            if ($finishingStageId && !$stages->contains('id', $finishingStageId)) throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
            $available = (int) $stages->sum('pieces_ok');
            $allocated = BarcodeLabel::where('work_order_id', $workOrderId)->count();
            if ($allocated + $total > $available) throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
            if ($finishingStageId && BarcodeLabel::where('finishing_stage_id', $finishingStageId)->count() + $total > (int) $stages->firstWhere('id', $finishingStageId)->pieces_ok) {
                throw new \InvalidArgumentException(__('erp.audit_barcode_limit'));
            }
            $labels = [];

            foreach ($sizes as $row) {
                for ($i = 0; $i < (int) $row['qty']; $i++) {
                    $labels[] = BarcodeLabel::create([
                        'work_order_id'      => $workOrder->id,
                        'finishing_stage_id' => $finishingStageId,
                        'product_id'         => $workOrder->product_id,
                        'size'               => $row['size'],
                        'barcode'            => strtoupper($workOrder->spk_number) . '-' . strtoupper($row['size']) . '-' . Str::upper(Str::random(6)),
                        'mrp'                => $row['mrp'],
                        'batch_number'       => $batchNumber,
                        'is_printed'         => false,
                    ]);
                }
            }

            return $labels;
        });
    }

    public function markPrinted(array $labelIds): int
    {
        return BarcodeLabel::whereIn('id', $labelIds)->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);
    }
}

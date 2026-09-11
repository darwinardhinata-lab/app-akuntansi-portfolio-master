<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\BarcodeLabel;
use App\Modules\Manufacturing\Models\WorkOrder;
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
        // Dibungkus transaction: barcode punya unique constraint di DB (lihat
        // migration mfg_barcode_labels). Tanpa transaction, collision Str::random(6)
        // di tengah loop akan menyisakan sebagian label sudah ter-insert (partial
        // batch) sementara sisanya gagal — membuat batch_number tidak konsisten.
        return DB::transaction(function () use ($workOrderId, $sizes, $batchNumber, $finishingStageId) {
            $workOrder = WorkOrder::findOrFail($workOrderId);
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

<?php

namespace App\Modules\Manufacturing\Support;

use App\Modules\Manufacturing\Models\MaterialLedger;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper moving-average cost khusus bahan baku manufaktur (mfg_yarns / mfg_fabrics).
 *
 * Ini adalah versi paralel dari pola moving-average yang sudah dipakai
 * PurchaseOrderService untuk `products`/`inventory_ledgers`, tapi diterapkan
 * ke `mfg_yarns`/`mfg_fabrics`/`mfg_material_ledgers` — supaya HPP bahan baku
 * (yarn, kain grey, kain finished) tetap akurat sepanjang rantai produksi
 * (procurement -> knitting -> processing -> cutting), sesuai alur di
 * MANUFACTURING_INTEGRATION.md.
 *
 * PENTING: $item (Yarn atau Fabric) HARUS sudah di-lockForUpdate() oleh
 * pemanggil, di dalam DB::transaction() yang sedang berjalan. Helper ini
 * tidak membuka transaction sendiri.
 */
class MaterialCostHelper
{
    /**
     * Stok masuk (IN) — dipakai saat MRN diterima, atau saat hasil
     * knitting/processing dicatat sebagai stok baru.
     *
     * @param Model  $item        Instance Yarn atau Fabric (sudah di-lock)
     * @param string $itemType    'YARN' atau 'FABRIC'
     * @param float  $qty
     * @param float  $unitCost    HPP per unit dari transaksi ini
     * @param string $date        Y-m-d
     * @param string $evidence    Nomor dokumen sumber (MRN-..., GFR-..., FR-...)
     * @param string $description
     * @return array{unit_cost: float, total_cost: float, new_stock: float, new_average_cost: float}
     */
    public static function receiveStock(Model $item, string $itemType, float $qty, float $unitCost, string $date, string $evidence, string $description): array
    {
        $now = now();
        $oldStock = (float) $item->stock_quantity;
        $oldMac   = (float) $item->average_cost;

        $totalCost = $qty * $unitCost;
        $newStock  = $oldStock + $qty;
        $newValue  = ($oldStock * $oldMac) + $totalCost;
        $newMac    = $newStock > 0 ? ($newValue / $newStock) : 0;

        $item->stock_quantity = $newStock;
        $item->average_cost   = $newMac;
        $item->save();

        MaterialLedger::create([
            'transaction_date'    => $date,
            'evidence_number'     => $evidence,
            'item_type'           => $itemType,
            'item_id'             => $item->getKey(),
            'type'                => 'IN',
            'qty'                 => $qty,
            'unit_cost'           => $unitCost,
            'total_cost'          => $totalCost,
            'running_qty'         => $newStock,
            'running_value'       => $newValue,
            'moving_average_cost' => $newMac,
            'description'         => $description,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return [
            'unit_cost'        => $unitCost,
            'total_cost'       => $totalCost,
            'new_stock'        => $newStock,
            'new_average_cost' => $newMac,
        ];
    }

    /**
     * Stok keluar (OUT) — dipakai saat yarn dikirim ke knitter, kain grey
     * dikirim ke processor, atau kain finished dipotong (cutting).
     * HPP yang dipakai adalah average_cost berjalan milik item (snapshot
     * saat transaksi terjadi) — konsisten dengan pola moving average.
     *
     * @throws \Exception jika stok tidak mencukupi
     */
    public static function issueStock(Model $item, string $itemType, float $qty, string $date, string $evidence, string $description): array
    {
        $oldStock = (float) $item->stock_quantity;
        $oldMac   = (float) $item->average_cost;

        if ($qty > $oldStock) {
            throw new \Exception(
                "Stok {$itemType} '{$item->getAttribute($itemType === 'YARN' ? 'yarn_code' : 'fabric_code')}' tidak mencukupi. " .
                "Tersedia: {$oldStock}, diminta: {$qty}."
            );
        }

        $now = now();
        $unitCost  = $oldMac; // snapshot HPP berjalan
        $totalCost = $qty * $unitCost;
        $newStock  = $oldStock - $qty;
        $newValue  = $newStock * $oldMac; // MAC tidak berubah saat OUT

        $item->stock_quantity = $newStock;
        $item->save();

        MaterialLedger::create([
            'transaction_date'    => $date,
            'evidence_number'     => $evidence,
            'item_type'           => $itemType,
            'item_id'             => $item->getKey(),
            'type'                => 'OUT',
            'qty'                 => $qty,
            'unit_cost'           => $unitCost,
            'total_cost'          => $totalCost,
            'running_qty'         => $newStock,
            'running_value'       => $newValue,
            'moving_average_cost' => $oldMac,
            'description'         => $description,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return [
            'unit_cost'  => $unitCost,
            'total_cost' => $totalCost,
            'new_stock'  => $newStock,
        ];
    }
}

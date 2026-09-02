<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryLedger;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Centralized Warehouse (Gudang) Inventory Sync Service.
 *
 * Ensures every time an Invoice (INV), Purchase Bill (BIL),
 * Sales Return (SR), or Purchase Return (PR) is imported via CSV
 * or created via controller, the system automatically records the
 * stock movement in `inventory_ledgers` (Kartu Stok) and updates
 * the `stock_quantity` in the `products` table.
 *
 * Business Rules & Mapping:
 *   INV (Sales Invoice)      -> Type: 'OUT' (Stok Keluar)
 *   PR  (Purchase Return)    -> Type: 'OUT' (Stok Keluar)
 *   BIL (Purchase Bill)      -> Type: 'IN'  (Stok Masuk)
 *   SR  (Sales Return)       -> Type: 'IN'  (Stok Masuk)
 *
 * Design Principles:
 *   - NO N+1 queries: products are preloaded by SKU in a single query.
 *   - Batch Processing: inventory_ledgers are inserted in chunks of 500.
 *   - Moving Average Cost (MAC) is recalculated on every IN transaction.
 *   - All operations are designed to run inside a DB::beginTransaction().
 */
class InventorySyncService
{
    /**
     * Document type to stock movement direction mapping.
     *
     * @var array<string, string>
     */
    public const STOCK_DIRECTIONS = [
        'INV' => 'OUT',  // Sales Invoice      -> Stok Keluar
        'PR'  => 'OUT',  // Purchase Return    -> Stok Keluar
        'BIL' => 'IN',   // Purchase Bill      -> Stok Masuk
        'SR'  => 'IN',   // Sales Return       -> Stok Masuk
    ];

    /**
     * Chunk size for batch inserting inventory_ledger records.
     * Prevents "too many placeholders" error (MySQL limit: 65,535).
     */
    public const CHUNK_SIZE = 500;

    /**
     * Process stock movements for a batch of items.
     *
     * This method:
     * 1. Maps the document type to IN/OUT direction.
     * 2. Preloads all products by SKU in a single query (no N+1).
     * 3. Calculates running_qty and moving_average_cost (MAC) for each item.
     * 4. Prepares batch arrays for inventory_ledgers and product updates.
     * 5. Executes bulk insert (chunked) and bulk update.
     *
     * @param array  $items           Array of items, each with keys:
     *                                'sku' (required), 'qty' (required),
     *                                'unit_cost' (optional, used for IN).
     * @param string $evidenceNumber  Document number (e.g., 'INV-20260730-0001').
     * @param string $transactionDate Date of the transaction (Y-m-d).
     * @param string $docType         Document type: INV, PR, BIL, or SR.
     * @param string $description     Description for the ledger entry.
     * @param bool   $execute         If true, executes DB writes. If false,
     *                                only prepares and returns data (dry-run).
     *
     * @return array{
     *     inventory_ledgers: array,
     *     product_updates: array,
     *     total_value: float,
     *     direction: string,
     *     cogs_value: float
     * }
     *
     * @throws Exception If docType is not recognized.
     */
    public function processStockMovements(
        array $items,
        string $evidenceNumber,
        string $transactionDate,
        string $docType,
        string $description = '',
        bool $execute = true
    ): array {
        // 1. Map doc type to direction
        $direction = self::STOCK_DIRECTIONS[$docType] ?? null;
        if ($direction === null) {
            throw new Exception("Unknown document type '{$docType}' for inventory sync. Valid types: " . implode(', ', array_keys(self::STOCK_DIRECTIONS)));
        }

        // Filter out items with zero or negative qty
        $items = array_filter($items, function ($item) {
            return (float) ($item['qty'] ?? 0) > 0;
        });

        if (empty($items)) {
            return [
                'inventory_ledgers' => [],
                'product_updates'   => [],
                'total_value'       => 0.0,
                'direction'         => $direction,
                'cogs_value'        => 0.0,
            ];
        }

        // 2. Collect all unique SKUs
        $skus = array_map(function ($item) {
            return $item['sku'];
        }, $items);
        $skus = array_unique($skus);

        // 3. Preload products by SKU in a SINGLE query (no N+1)
        //    lockForUpdate ensures no race condition on stock_quantity
        $products = Product::whereIn('sku', $skus)
            ->lockForUpdate()
            ->get()
            ->keyBy('sku');

        // 4. Prepare arrays
        $inventoryLedgers = [];
        $productStockUpdates = []; // [product_id => ['stock_quantity' => x, 'average_cost' => y]]
        $totalValue = 0.0;
        $cogsValue = 0.0;
        $now = now();

        // 5. Process each item
        foreach ($items as $item) {
            $sku = $item['sku'];
            $qty = (float) $item['qty'];
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            $product = $products->get($sku);
            if (!$product) {
                // Skip items whose SKU doesn't exist in products table
                continue;
            }

            $productId = $product->id;
            $oldStock = (float) ($product->stock_quantity ?? 0);
            $oldMac = (float) ($product->average_cost ?? 0);

            if ($direction === 'IN') {
                // ---- Stock IN (BIL, SR) ----
                // Add to stock, recalculate Moving Average Cost
                $newStock = $oldStock + $qty;
                $newValue = ($oldStock * $oldMac) + ($qty * $unitCost);
                $newMac = $newStock > 0 ? ($newValue / $newStock) : 0;
                $lineTotal = $qty * $unitCost;

                $productStockUpdates[$productId] = [
                    'stock_quantity' => $newStock,
                    'average_cost'   => $newMac,
                ];

                // Update in-memory model for multi-item-per-SKU accuracy
                $product->stock_quantity = $newStock;
                $product->average_cost = $newMac;

                $inventoryLedgers[] = [
                    'transaction_date'    => $transactionDate,
                    'evidence_number'     => $evidenceNumber,
                    'product_id'          => $productId,
                    'type'                => 'IN',
                    'qty'                 => $qty,
                    'unit_cost'           => $unitCost,
                    'total_cost'          => $lineTotal,
                    'running_qty'         => $newStock,
                    'running_value'       => $newStock * $newMac,
                    'moving_average_cost' => $newMac,
                    'description'         => $description,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                $totalValue += $lineTotal;
            } else {
                // ---- Stock OUT (INV, PR) ----
                // Subtract from stock, use current MAC as unit cost
                $newStock = $oldStock - $qty;
                $effectiveCost = $oldMac; // Use current MAC for OUT
                $lineTotal = $qty * $effectiveCost;

                $productStockUpdates[$productId] = [
                    'stock_quantity' => $newStock,
                ];

                // Update in-memory model for multi-item-per-SKU accuracy
                $product->stock_quantity = $newStock;

                $inventoryLedgers[] = [
                    'transaction_date'    => $transactionDate,
                    'evidence_number'     => $evidenceNumber,
                    'product_id'          => $productId,
                    'type'                => 'OUT',
                    'qty'                 => $qty,
                    'unit_cost'           => $effectiveCost,
                    'total_cost'          => $lineTotal,
                    'running_qty'         => $newStock,
                    'running_value'       => $newStock * $effectiveCost,
                    'moving_average_cost' => $effectiveCost,
                    'description'         => $description,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                $cogsValue += $lineTotal;
            }
        }

        // 6. Execute batch operations (if not dry-run)
        if ($execute && !empty($inventoryLedgers)) {
            // BATCH INSERT: inventory_ledgers (chunked to prevent memory issues)
            foreach (array_chunk($inventoryLedgers, self::CHUNK_SIZE) as $chunk) {
                InventoryLedger::insert($chunk);
            }

            // BULK UPDATE: products stock_quantity (and average_cost for IN)
            foreach ($productStockUpdates as $productId => $updates) {
                Product::where('id', $productId)->update($updates);
            }
        }

        return [
            'inventory_ledgers' => $inventoryLedgers,
            'product_updates'   => $productStockUpdates,
            'total_value'       => $totalValue,
            'direction'         => $direction,
            'cogs_value'        => $cogsValue,
        ];
    }

    /**
     * Get the stock movement direction for a document type.
     *
     * @param string $docType Document type: INV, PR, BIL, or SR.
     * @return string 'IN' or 'OUT'.
     */
    public static function getDirection(string $docType): string
    {
        return self::STOCK_DIRECTIONS[$docType] ?? 'IN';
    }

    /**
     * Check if a document type requires inventory sync.
     *
     * @param string $docType Document type: INV, PR, BIL, or SR.
     * @return bool
     */
    public static function requiresSync(string $docType): bool
    {
        return isset(self::STOCK_DIRECTIONS[$docType]);
    }

    /**
     * Reverse stock movements for a given evidence number.
     * Used when deleting/voiding a document.
     *
     * @param string $evidenceNumber The document number to reverse.
     * @param string $docType        Document type (for direction lookup).
     * @return array{reversed_qty: array, reversed_value: float}
     */
    public function reverseStockMovements(string $evidenceNumber, string $docType): array
    {
        $direction = self::STOCK_DIRECTIONS[$docType] ?? 'IN';
        $reverseDirection = $direction === 'IN' ? 'OUT' : 'IN';

        // Aggregate stock changes per product
        $stockChanges = InventoryLedger::where('evidence_number', $evidenceNumber)
            ->where('type', $direction)
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_cost) as total_value'))
            ->get();

        $reversedQty = [];
        $reversedValue = 0.0;

        foreach ($stockChanges as $row) {
            $productId = $row->product_id;
            $qty = (int) $row->total_qty;
            $value = (float) $row->total_value;

            $product = Product::find($productId);
            if ($product) {
                $newStock = $direction === 'IN'
                    ? $product->stock_quantity - $qty
                    : $product->stock_quantity + $qty;

                $product->update(['stock_quantity' => $newStock]);
                $reversedQty[$productId] = $qty;
                $reversedValue += $value;
            }
        }

        // Delete the inventory ledger entries
        InventoryLedger::where('evidence_number', $evidenceNumber)
            ->where('type', $direction)
            ->delete();

        return [
            'reversed_qty'   => $reversedQty,
            'reversed_value' => $reversedValue,
        ];
    }
}

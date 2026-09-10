<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\InventoryLedger;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseOrderService
{
    public function receivePartialOrder($poId, $receiveDate, $itemsToReceive, $billNumber = null, $dueDate = null)
    {
        DB::beginTransaction();
        try {
            // B13 FIX: Lock baris PO agar qty_received & status tidak bentrok saat receive paralel
            $po = PurchaseOrder::with('details')->lockForUpdate()->findOrFail($poId);

            if ($po->status === 'RECEIVED') {
                throw new Exception("Gagal: Purchase Order sudah komplit (RECEIVED).");
            }

            // Coba cocokkan payment plan uang muka:
            // 1. Cek by synthetic PO number (PO-{no_transaksi}) — backward compatible
            $noTransaksiPP = str_replace('PO-', '', $po->po_number);
            $paymentPlan = DB::table('transaksi_payment_plan')
                ->where('no_transaksi', $noTransaksiPP)
                ->where('kategori_payment', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')
                ->first();

            // 2. Fallback: cek by ref_po_number (real PO dari sumber eksternal) — Fix #5
            if (!$paymentPlan && !empty($po->ref_po_number)) {
                $paymentPlan = DB::table('transaksi_payment_plan')
                    ->where('ref_po_number', $po->po_number)
                    ->where('kategori_payment', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')
                    ->first();
            }

            $akunKredit = $paymentPlan ? config('coa.uang_muka_beli') : config('coa.hutang_usaha');

            $totalNominalDiterimaSkrg = 0;
            $allCompleted = true;
            // FIX: Gunakan Bill Number asli dari supplier untuk Jurnal & Kartu Stok
            // P2-2: Gunakan DocumentSequence (row-lock) untuk menghindari race condition
            $evidenceNumber = $billNumber ?? \App\Support\DocumentSequence::generateSecure(
                'purchase_bills', 'bill_number', 'BIL-' . $po->po_number . '-'
            );
            $now = now();

            // FIX ANTI-DOBEL: Cek apakah evidence_number (nomor Bill) sudah pernah dijurnal
            // sebelum membuat JournalHeader baru. Ini mencegah double-posting saat:
            // 1. User terima barang via UI lalu bagian lain import:bil dengan nomor Bill yang sama
            // 2. Proses receive dijalankan 2x untuk bill yang sama
            $existingJournal = JournalHeader::where('evidence_number', $evidenceNumber)
                ->where('transaction_type', 'Purchase Bill')
                ->exists();
            if ($existingJournal) {
                throw new Exception(
                    "Gagal: Nomor Bill '{$evidenceNumber}' sudah memiliki jurnal di sistem. " .
                    "Kemungkinan data sudah di-import via import:bil atau sudah diterima sebelumnya. " .
                    "Gunakan void receipt jika perlu koreksi, jangan receive ulang."
                );
            }

            $itemCodes = $po->details->pluck('item_code')->filter()->unique()->toArray();
            // FIX: Kunci baris produk untuk mencegah Lost Update saat kalkulasi Moving Average Cost
            $products = Product::whereIn('sku', $itemCodes)->lockForUpdate()->get()->keyBy('sku');

            // OPTIMASI N+1: Kumpulkan semua perubahan, eksekusi batch di luar loop
            $detailQtyUpdates = [];    // [detail_id => new_qty_received]
            $productUpdates = [];      // [product_id => ['stock_quantity' => x, 'average_cost' => y]]
            $inventoryLedgers = [];

            foreach ($po->details as $detail) {
                $qtyTerimaSkrg = isset($itemsToReceive[$detail->id]) ? (int)$itemsToReceive[$detail->id] : 0;

                // H2 FIX: Validasi qty receive tidak boleh melebihi qty PO
                $maxReceivable = $detail->qty - $detail->qty_received;
                if ($qtyTerimaSkrg > $maxReceivable) {
                    throw new Exception("Qty receive lewat batas untuk item {$detail->item_code}. Maksimal dapat diterima: {$maxReceivable}, diminta: {$qtyTerimaSkrg}");
                }

                if ($qtyTerimaSkrg > 0) {
                    $nilaiAsetSkrg = $qtyTerimaSkrg * $detail->price;
                    $totalNominalDiterimaSkrg += $nilaiAsetSkrg;
                    $detailQtyUpdates[$detail->id] = $detail->qty_received + $qtyTerimaSkrg;

                    $product = $products->get($detail->item_code);
                    if ($product) {
                        $oldStock = $product->stock_quantity ?? 0;
                        $oldMac   = $product->average_cost ?? 0;
                        $newStock = $oldStock + $qtyTerimaSkrg;
                        $newValue = ($oldStock * $oldMac) + $nilaiAsetSkrg;
                        $newMac   = $newStock > 0 ? ($newValue / $newStock) : 0;

                        // Update in-memory untuk kalkulasi multi-item per produk yang akurat
                        $product->stock_quantity = $newStock;
                        $product->average_cost   = $newMac;

                        $productUpdates[$product->id] = ['stock_quantity' => $newStock, 'average_cost' => $newMac];

                        $inventoryLedgers[] = [
                            'transaction_date'    => $receiveDate,
                            'evidence_number'     => $evidenceNumber,
                            'product_id'          => $product->id,
                            'type'                => 'IN',
                            'qty'                 => $qtyTerimaSkrg,
                            'unit_cost'           => $detail->price,
                            'total_cost'          => $nilaiAsetSkrg,
                            'running_qty'         => $newStock,
                            'running_value'       => $newValue,
                            'moving_average_cost' => $newMac,
                            'description'         => "Penerimaan PO: {$po->po_number} dari {$po->contact_name}",
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ];
                    }
                }

                $newQtyReceived = $detailQtyUpdates[$detail->id] ?? $detail->qty_received;
                if ($newQtyReceived < $detail->qty) {
                    $allCompleted = false;
                }
            }

            if ($totalNominalDiterimaSkrg <= 0) {
                throw new Exception("Tidak ada Quantity barang yang diinput untuk diterima.");
            }

            // BATCH UPDATE: Detail PO (bukan $detail->save() per baris)
            foreach ($detailQtyUpdates as $detailId => $newQty) {
                DB::table('purchase_order_details')
                    ->where('id', $detailId)
                    ->update(['qty_received' => $newQty, 'updated_at' => $now]);
            }

            // BATCH UPDATE: Stok & HPP Produk
            foreach ($productUpdates as $productId => $updates) {
                Product::where('id', $productId)->update($updates);
            }

            // BATCH INSERT: Kartu Stok
            if (!empty($inventoryLedgers)) {
                InventoryLedger::insert($inventoryLedgers);
            }

            // Single INSERT: Jurnal Keuangan
            $journalHeader = JournalHeader::create([
                'transaction_date' => $receiveDate,
                'evidence_number'  => $evidenceNumber,
                'description'      => "Tagihan Pembelian (Bill) Ref PO: {$po->po_number} - Supplier: {$po->contact_name}",
                'source_doc_no'    => $billNumber,
                'transaction_type' => 'Purchase Bill',
            ]);

            JournalDetail::insert([
                ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.persediaan'), 'helper_code' => null, 'position' => 'DEBET',  'amount' => $totalNominalDiterimaSkrg, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journalHeader->getKey(), 'account_code' => $akunKredit, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $totalNominalDiterimaSkrg, 'created_at' => $now, 'updated_at' => $now],
            ]);

            // FIX: Link journal_id FK ke PurchaseBill (backlog item "journal_id FK columns")
            DB::table('purchase_bills')
                ->where('bill_number', $evidenceNumber)
                ->update(['journal_id' => $journalHeader->getKey(), 'updated_at' => $now]);

            $po->status = $allCompleted ? 'RECEIVED' : 'PARTIAL';
            $po->save();

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function voidReceipt($poId)
    {
        DB::beginTransaction();
        try {
            // B13 FIX: Lock baris PO agar void tidak bentrok dengan receive paralel
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);

            if ($po->status === 'APPROVED') {
                throw new Exception("Gagal: PO ini belum pernah diterima.");
            }

            // FIX: Temukan semua evidence_number terkait penerimaan PO ini melalui Inventory Ledger
            // Karena saat penerimaan, evidence bisa berupa 'BIL-...' atau inputan manual user, BUKAN RCV-
            $evidenceNumbers = DB::table('inventory_ledgers')
                ->where('description', 'LIKE', "Penerimaan PO: {$po->po_number}%")
                ->pluck('evidence_number')
                ->toArray();

            if (!empty($evidenceNumbers)) {
                // 1. Batch delete jurnal akuntansi
                $journalIds = JournalHeader::whereIn('evidence_number', $evidenceNumbers)->pluck('journal_id');
                if ($journalIds->isNotEmpty()) {
                    DB::table('journal_details')->whereIn('journal_id', $journalIds)->delete();
                    DB::table('journal_headers')->whereIn('journal_id', $journalIds)->delete();
                }

                // 2. Kalkulasi balik stok dari InventoryLedger (Kembalikan ke posisi awal)
                $stockChanges = DB::table('inventory_ledgers')
                    ->whereIn('evidence_number', $evidenceNumbers)
                    ->where('type', 'IN')
                    ->groupBy('product_id')
                    ->select('product_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_cost) as total_value'))
                    ->get();

                foreach ($stockChanges as $row) {
                    $product = Product::find($row->product_id);
                    if ($product) {
                        $newStock = $product->stock_quantity - (int)$row->total_qty;

                        $currentValue = $product->stock_quantity * $product->average_cost;
                        $newValue = $currentValue - (float) $row->total_value;
                        $newAverage = $newStock > 0 ? max(0, $newValue / $newStock) : 0;

                        $product->update([
                            'stock_quantity' => $newStock,
                            'average_cost' => $newAverage
                        ]);
                    }
                }

                // 3. Batch delete kartu stok (Inventory Ledger)
                InventoryLedger::whereIn('evidence_number', $evidenceNumbers)->delete();
            }

            // Kembalikan status PO menjadi belum diterima
            PurchaseOrderDetail::where('purchase_order_id', $po->id)->update(['qty_received' => 0]);

            $po->status = 'APPROVED';
            $po->save();

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

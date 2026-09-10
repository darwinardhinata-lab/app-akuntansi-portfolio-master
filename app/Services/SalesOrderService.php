<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\InventoryLedger;
use Illuminate\Support\Facades\DB;
use App\Support\JournalBalanceValidator;
use Exception;

class SalesOrderService
{
    public function createInvoiceAndShip(int $soId, string $shipDate, array $actualItems, array $financials, bool $skipJournal = false, ?string $externalInvoiceNumber = null): bool
    {
        DB::beginTransaction();
        try {
            // FIX #1: tangkap komponen yang sebelumnya hilang agar jurnal tetap balance
            $shippingDiscount = (float) ($financials['shipping_discount'] ?? 0);
            $otherCost        = (float) ($financials['other_cost'] ?? 0);
            $returnRemaining  = (float) ($financials['return_remaining'] ?? 0);

            // B13 FIX: Lock baris SO agar tidak ada race condition status saat request paralel
            $so = SalesOrder::lockForUpdate()->findOrFail($soId);

            if ($so->status === 'SHIPPED') {
                throw new Exception("Sales Order ini sudah selesai dan fakturnya sudah tercetak.");
            }

            // FIX ANTI-DOBEL: Gunakan nomor invoice asli dari sumber eksternal jika disediakan.
            // Ini menyatukan sumber kebenaran nomor faktur antara jalur webhook dan import:inv,
            // sehingga import:inv otomatis idempoten terhadap data yang sudah masuk lewat webhook.
            // Jika tidak ada (jalur manual UI / import:sales), generate sendiri seperti sebelumnya.
            if (!empty($externalInvoiceNumber)) {
                $invoiceNumber = $externalInvoiceNumber;
                // Cek idempotensi: jika invoice dengan nomor ini sudah ada, skip (sudah pernah diproses)
                $existingInvoice = SalesInvoice::where('invoice_number', $invoiceNumber)->first();
                if ($existingInvoice) {
                    throw new Exception(
                        "Faktur dengan nomor '{$invoiceNumber}' sudah ada di sistem. " .
                        "Kemungkinan data sudah diproses via webhook atau import:inv. Dilewati."
                    );
                }
            } else {
                // FIX: Gunakan sequence dari DB — rand() berisiko duplikat saat concurrent request
                $invoicePrefix = 'INV-' . date('ymd') . '-';
                $lastInv = DB::table('sales_invoices')
                    ->where('invoice_number', 'like', $invoicePrefix . '%')
                    ->orderByDesc('invoice_number')
                    ->value('invoice_number');
                $seq = $lastInv ? ((int) substr($lastInv, strlen($invoicePrefix)) + 1) : 1;
                $invoiceNumber = $invoicePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }

            // C5 FIX: Hitung ulang nilai dari actualItems untuk validasi server-side
            $calculatedSubTotal = 0;
            foreach ($actualItems as $item) {
                $qty = (int) ($item['qty'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                if ($qty > 0) {
                    $calculatedSubTotal += $qty * $price;
                }
            }

            // Validasi nilai yang dikirim dari request dengan kalkulasi server-side
            // Tolerance 0.01 untuk menampung floating point difference
            $requestSubTotal = (float) ($financials['sub_total'] ?? 0);
            $requestGrandTotal = (float) ($financials['grand_total'] ?? 0);
            $requestDiscAmount = (float) ($financials['disc_amount'] ?? 0);
            $requestTaxAmount = (float) ($financials['tax_amount'] ?? 0);
            $requestShipping = (float) ($financials['shipping_cost'] ?? 0);

            if (abs($requestSubTotal - $calculatedSubTotal) > 0.01) {
                throw new Exception("Validasi gagal: sub_total tidak sesuai. Dikirim: {$requestSubTotal}, Harus: {$calculatedSubTotal}");
            }

            $calculatedGrandTotal = $calculatedSubTotal - $requestDiscAmount + $requestTaxAmount + $requestShipping;
            if (abs($requestGrandTotal - $calculatedGrandTotal) > 0.01) {
                throw new Exception("Validasi gagal: grand_total tidak sesuai perhitungan. Dikirim: {$requestGrandTotal}, Harus: {$calculatedGrandTotal}");
            }

            $invoice = SalesInvoice::create([
                'invoice_number'   => $invoiceNumber,
                'sales_order_id'   => $so->id,
                'transaction_date' => $shipDate,
                'contact_name'     => $so->contact_name,
                'sub_total'        => $calculatedSubTotal,
                'disc_amount'      => $requestDiscAmount,
                'tax_amount'       => $requestTaxAmount,
                'shipping_cost'    => $requestShipping,
                'grand_total'      => $calculatedGrandTotal,
                'payment_status'   => 'UNPAID',
            ]);

            $totalCogsValue = 0;
            $invoiceDetails = [];
            $inventoryLedgers = [];
            // OPTIMASI N+1: Kumpulkan perubahan stok, update batch di luar loop
            $productStockUpdates = []; // [product_id => new_stock]

            $itemCodes = array_column($actualItems, 'item_code');
            // FIX: Kunci baris produk selama transaksi agar stok akurat saat dipotong paralel
            $products = Product::whereIn('sku', $itemCodes)->lockForUpdate()->get()->keyBy('sku');

            foreach ($actualItems as $item) {
                $qtyKeluar = (int) $item['qty'];
                if ($qtyKeluar <= 0) continue;

                $product = $products->get($item['item_code']);
                $productId = $product ? $product->id : null;
                $currentMac = $product ? ($product->average_cost ?? 0) : 0;
                $lineCogsValue = $qtyKeluar * $currentMac;
                $totalCogsValue += $lineCogsValue;

                $invoiceDetails[] = [
                    'sales_invoice_id' => $invoice->id,
                    'product_id'       => $productId,
                    'item_code'        => $item['item_code'],
                    'description'      => $product ? $product->name : 'Produk Custom',
                    'price'            => $item['price'],
                    'qty_actual'       => $qtyKeluar,
                    'disc_amount'      => 0,
                    'amount'           => $qtyKeluar * $item['price'],
                    'is_substitution'  => $item['is_substitution'] ?? false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];

                if ($product) {
                    // H1 FIX: Validasi stok cukup sebelum mengurangi
                    if ($qtyKeluar > $product->stock_quantity) {
                        throw new Exception("Stok tidak cukup untuk produk {$product->name} (SKU: {$product->sku}). Stok tersedia: {$product->stock_quantity}, Diminta: {$qtyKeluar}");
                    }

                    $newStock = $product->stock_quantity - $qtyKeluar;
                    // Update in-memory agar multi-item per produk akurat
                    $product->stock_quantity = $newStock;
                    $productStockUpdates[$product->id] = $newStock;

                    $inventoryLedgers[] = [
                        'transaction_date'    => $shipDate,
                        'evidence_number'     => $invoiceNumber,
                        'product_id'          => $product->id,
                        'type'                => 'OUT',
                        'qty'                 => $qtyKeluar,
                        'unit_cost'           => $currentMac,
                        'total_cost'          => $lineCogsValue,
                        'running_qty'         => $newStock,
                        'running_value'       => $newStock * $currentMac,
                        'moving_average_cost' => $currentMac,
                        'description'         => "Faktur Penjualan: {$invoiceNumber} (Reff: {$so->so_number})",
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }
            }

            if (!empty($invoiceDetails)) {
                SalesInvoiceDetail::insert($invoiceDetails);
            }

            // OPTIMASI N+1: Batch update stok — sebelumnya $product->save() per item
            foreach ($productStockUpdates as $productId => $newStock) {
                Product::where('id', $productId)->update(['stock_quantity' => $newStock]);
            }

            if (!empty($inventoryLedgers)) {
                InventoryLedger::insert($inventoryLedgers);
            }

            if (!$skipJournal && ($invoice->grand_total > 0 || $totalCogsValue > 0)) {
                $journalHeader = JournalHeader::create([
                    'transaction_date' => $shipDate,
                    'evidence_number'  => $invoiceNumber,
                    'description'      => "Faktur Penjualan: {$invoiceNumber} - Pelanggan: {$so->contact_name}",
                    'source_doc_no'    => $invoiceNumber,
                    'transaction_type' => 'Faktur',
                    'tags'             => $so->store_name,
                ]);

                $primaryId = $journalHeader->getKey();
                $now = now();
                $journalLines = [];

                if ($invoice->grand_total > 0) {
                    // Piutang Usaha
                    $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.piutang_usaha'), 'position' => 'DEBET',  'amount' => $invoice->grand_total,  'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];

                    // Pecah Potongan sesuai mapping COA
                    if ($invoice->disc_amount > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_penjualan'), 'position' => 'DEBET', 'amount' => $invoice->disc_amount, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($shippingDiscount > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_ongkir'), 'position' => 'DEBET', 'amount' => $shippingDiscount, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    
                    $otherDiscAndReturn = ($so->other_discount ?? 0) + $returnRemaining;
                    if ($otherDiscAndReturn > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_lain'), 'position' => 'DEBET', 'amount' => $otherDiscAndReturn, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }

                    // Penjualan
                    $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.penjualan'), 'position' => 'KREDIT', 'amount' => $invoice->sub_total,   'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    
                    // Ongkos Kirim
                    if ($invoice->shipping_cost > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.ongkos_kirim'), 'position' => 'KREDIT', 'amount' => $invoice->shipping_cost, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    
                    if ($invoice->tax_amount > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.pajak_keluaran'), 'position' => 'KREDIT', 'amount' => $invoice->tax_amount, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    
                    // Biaya Lain-Lain (Ditagihkan ke pelanggan -> Pendapatan Lain Marketplace / Selisih Ongkir +)
                    if ($otherCost > 0) {
                        $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.biaya_lain'), 'position' => 'KREDIT', 'amount' => $otherCost, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                
                if ($totalCogsValue > 0) {
                    $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.hpp'), 'position' => 'DEBET',  'amount' => $totalCogsValue, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                    $journalLines[] = ['journal_id' => $primaryId, 'account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => $totalCogsValue, 'helper_code' => null, 'created_at' => $now, 'updated_at' => $now];
                }

                // FIX BALANCE: guard mutlak menggunakan validator terpusat
                if (!JournalBalanceValidator::isBalanced($journalLines)) {
                    $selisih = JournalBalanceValidator::getDifference($journalLines);
                    throw new Exception("Jurnal tidak balance untuk SO {$so->so_number}. Selisih: Rp " . number_format($selisih, 2, ',', '.'));
                }

                if (!empty($journalLines)) {
                    JournalDetail::insert($journalLines);
                }

                // FIX: Link journal_id FK ke SalesInvoice (backlog item "journal_id FK columns")
                $invoice->update(['journal_id' => $journalHeader->getKey()]);
            }

            // FIX: Tulis balik referensi faktur ke Sales Order agar kolom invoice_id/invoice_no
            // (yang sudah ada di skema DB) terisi konsisten, bukan selalu NULL.
            $so->invoice_id = (string) $invoice->id;
            $so->invoice_no = $invoice->invoice_number;
            $so->status = 'SHIPPED';
            $so->save();

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function voidShipment(int $soId): bool
    {
        DB::beginTransaction();
        try {
            // B13 FIX: Lock baris SO agar void tidak bentrok dengan ship paralel
            $so = SalesOrder::lockForUpdate()->findOrFail($soId);

            if ($so->status !== 'SHIPPED') {
                throw new Exception("Gagal: SO belum berstatus SHIPPED.");
            }

            $invoice = SalesInvoice::where('sales_order_id', $so->id)->first();

            if ($invoice) {
                $evidenceNumber = $invoice->invoice_number;

                // Batch delete jurnal
                $journalIds = JournalHeader::where('evidence_number', $evidenceNumber)->pluck('journal_id');
                if ($journalIds->isNotEmpty()) {
                    DB::table('journal_details')->whereIn('journal_id', $journalIds)->delete();
                    DB::table('journal_headers')->whereIn('journal_id', $journalIds)->delete();
                }

                // OPTIMASI N+1: Agregasi delta stok dalam 1 query, bukan loop delete + save
                $stockChanges = DB::table('inventory_ledgers')
                    ->where('evidence_number', $evidenceNumber)
                    ->where('type', 'OUT')
                    ->groupBy('product_id')
                    ->select('product_id', DB::raw('SUM(qty) as total_qty'))
                    ->pluck('total_qty', 'product_id');

                foreach ($stockChanges as $productId => $qty) {
                    Product::where('id', $productId)->increment('stock_quantity', (int)$qty);
                }

                // Batch delete kartu stok
                InventoryLedger::where('evidence_number', $evidenceNumber)->delete();

                $invoice->delete();
            }

            $so->status = 'APPROVED';
            $so->save();

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

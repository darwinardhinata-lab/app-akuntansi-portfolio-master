<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Support\NumberParser;
use App\Support\JournalBalanceValidator;
use App\Services\InventorySyncService;

class FastImportINV extends Command
{
    protected $signature = 'import:inv {file}';
    protected $description = 'Import Massal CSV Sales Invoice (INV) via Terminal — otomatis potong stok Gudang (OUT)';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        DB::disableQueryLog();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            return $this->error("❌ File tidak ditemukan: {$filePath}");
        }

        $handle = fopen($filePath, "r");
        if (!$handle) return $this->error("❌ Gagal membuka file CSV.");

        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $headerLine = fgets($handle);
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $headerLine = preg_replace('/\r\n|\r/', "\n", $headerLine);
        $headerCols = str_getcsv(strtolower($headerLine), $delimiter, '"', '\\');
        $headerCols = array_map('trim', $headerCols);

        $findCol = function($keywords) use ($headerCols) {
            foreach ($headerCols as $idx => $colName) {
                foreach ($keywords as $key) {
                    if (stripos($colName, $key) !== false) return $idx;
                }
            }
            return -1;
        };

        $idxDate    = $findCol(['tanggal', 'date']);
        $idxNoFaktur = $findCol(['no faktur', 'invoice', 'inv']);
        $idxSku     = $findCol(['sku', 'item code', 'kode barang']);
        $idxQty     = $findCol(['qty', 'quantity', 'jumlah']);
        $idxPrice   = $findCol(['harga', 'price', 'harga jual']);
        $idxDisc    = $findCol(['diskon', 'disc', 'discount']);
        $idxCustomer = $findCol(['pelanggan', 'customer']);
        $idxDesc    = $findCol(['keterangan', 'deskripsi', 'description']);

        if ($idxDate == -1) $idxDate = 0;
        if ($idxNoFaktur == -1) $idxNoFaktur = 1;
        if ($idxSku == -1) $idxSku = 2;
        if ($idxQty == -1) $idxQty = 3;
        if ($idxPrice == -1) $idxPrice = 4;
        if ($idxDisc == -1) $idxDisc = 5;
        if ($idxCustomer == -1) $idxCustomer = 6;
        if ($idxDesc == -1) $idxDesc = 7;

        $this->info("🚀 Memulai import Sales Invoice (INV → OUT). Delimiter: '{$delimiter}'");

        $this->info("⏳ Memuat master produk (SKU) ke dalam RAM...");
        $products = DB::table('products')->pluck('id', 'sku')->toArray();

        $documents = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter, '"', '\\');
            }
            if (count($row) < 7) continue;

            $tanggal  = trim($row[$idxDate] ?? '');
            $noFaktur = trim($row[$idxNoFaktur] ?? '');
            $sku      = trim($row[$idxSku] ?? '');
            $qty      = (int) preg_replace('/[^0-9\-]/', '', $row[$idxQty] ?? '0');
            $price    = NumberParser::parseDecimal($row[$idxPrice] ?? '0');
            $disc     = NumberParser::parseDecimal($row[$idxDisc] ?? '0');
            $customer = trim($row[$idxCustomer] ?? '');
            $desc     = trim($row[$idxDesc] ?? '');

            if (empty($noFaktur) || strtolower($noFaktur) === 'no faktur') continue;
            if (empty($sku) || $qty <= 0) continue;

            $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des'], ['May', 'Oct', 'Aug', 'Dec'], $tanggal));
            $date = $time ? date('Y-m-d', $time) : date('Y-m-d');

            if (!isset($documents[$noFaktur])) {
                $documents[$noFaktur] = [
                    'invoice_number' => $noFaktur,
                    'transaction_date' => $date,
                    'contact_name' => $customer,
                    'items' => [],
                ];
            }

            $documents[$noFaktur]['items'][] = [
                'sku'       => $sku,
                'qty'       => $qty,
                'price'     => $price,
                'disc_amount' => $disc,
                'description' => $desc,
            ];

            $count++;
        }
        fclose($handle);

        if (empty($documents)) {
            return $this->error("❌ Tidak ada data valid di dalam CSV.");
        }

        $this->info("📋 Ditemukan {$count} baris item dalam " . count($documents) . " dokumen INV.");

        DB::beginTransaction();
        try {
            $invCount = 0;
            foreach ($documents as $noFaktur => $doc) {
                $existing = SalesInvoice::where('invoice_number', $noFaktur)->first();
                if ($existing) {
                    $this->warn("⚠️  INV {$noFaktur} sudah ada, dilewati.");
                    continue;
                }

                $subTotal = 0;
                $totalDisc = 0;
                foreach ($doc['items'] as $item) {
                    $lineTotal = $item['price'] * $item['qty'];
                    $subTotal += $lineTotal;
                    $totalDisc += $item['disc_amount'];
                }
                $grandTotal = $subTotal - $totalDisc;

                // FIX ANTI-DOBEL: Cross-path guard — cek apakah ada SO yang sudah SHIPPED
                // untuk customer + tanggal + grand_total yang sama. Jika ada, berarti penjualan
                // ini sudah dicatat via webhook/import:sales, jangan import ulang via import:inv.
                $matchingShippedSo = \App\Models\SalesOrder::where('status', 'SHIPPED')
                    ->where('contact_name', $doc['contact_name'] ?: 'Unknown Customer')
                    ->whereDate('transaction_date', $doc['transaction_date'])
                    ->where('grand_total', $grandTotal)
                    ->exists();
                if ($matchingShippedSo) {
                    $this->warn("⚠️  INV {$noFaktur} dilewati: kemungkinan sudah tercatat via SO/Webhook (customer+tanggal+total cocok).");
                    continue;
                }

                $invoice = SalesInvoice::create([
                    'invoice_number'   => $doc['invoice_number'],
                    'sales_order_id'   => null,
                    'transaction_date' => $doc['transaction_date'],
                    'contact_name'     => $doc['contact_name'] ?: 'Unknown Customer',
                    'sub_total'        => $subTotal,
                    'disc_amount'      => $totalDisc,
                    'tax_amount'       => 0,
                    'shipping_cost'    => 0,
                    'grand_total'      => $grandTotal,
                    'payment_status'   => 'UNPAID',
                ]);

                $invoiceDetails = [];
                $invItems = [];
                foreach ($doc['items'] as $item) {
                    $product = $products[$item['sku']] ?? null;
                    $qty = $item['qty'];
                    $lineTotal = ($item['price'] * $qty) - $item['disc_amount'];

                    $invoiceDetails[] = [
                        'sales_invoice_id' => $invoice->id,
                        'product_id'       => $product,
                        'item_code'        => $item['sku'],
                        'description'      => $item['description'] ?: $item['sku'],
                        'price'            => $item['price'],
                        'qty_actual'       => $qty,
                        'disc_amount'      => $item['disc_amount'],
                        'amount'           => $lineTotal,
                        'is_substitution'  => false,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];

                    $invItems[] = [
                        'sku'       => $item['sku'],
                        'qty'       => $qty,
                        'unit_cost' => 0,
                    ];
                }

                if (!empty($invoiceDetails)) SalesInvoiceDetail::insert($invoiceDetails);

                // INTEGRASI GUDANG: INV → OUT (Stok Keluar)
                $inventoryService = new InventorySyncService();
                $invResult = $inventoryService->processStockMovements(
                    $invItems,
                    $invoice->invoice_number,
                    $doc['transaction_date'],
                    'INV',
                    "Faktur Penjualan: {$invoice->invoice_number}",
                    true
                );
                $totalCogsValue = $invResult['cogs_value'];

                // Create Journal
                if ($grandTotal > 0 || $totalCogsValue > 0) {
                    $journalHeader = JournalHeader::create([
                        'transaction_date' => $doc['transaction_date'],
                        'evidence_number'  => $invoice->invoice_number,
                        'description'      => "Faktur Penjualan: {$invoice->invoice_number} - {$invoice->contact_name}",
                        'source_doc_no'    => $invoice->invoice_number,
                        'transaction_type' => 'Faktur',
                    ]);

                    $jDetails = [];
                    if ($grandTotal > 0) {
                        $jDetails[] = ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.piutang_usaha'), 'position' => 'DEBET', 'amount' => $grandTotal, 'created_at' => $now, 'updated_at' => $now];
                        if ($totalDisc > 0) {
                            $jDetails[] = ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.diskon_penjualan'), 'position' => 'DEBET', 'amount' => $totalDisc, 'created_at' => $now, 'updated_at' => $now];
                        }
                        $jDetails[] = ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.penjualan'), 'position' => 'KREDIT', 'amount' => $subTotal, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($totalCogsValue > 0) {
                        $jDetails[] = ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.hpp'), 'position' => 'DEBET', 'amount' => $totalCogsValue, 'created_at' => $now, 'updated_at' => $now];
                        $jDetails[] = ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => $totalCogsValue, 'created_at' => $now, 'updated_at' => $now];
                    }

                    if (!JournalBalanceValidator::isBalanced($jDetails)) {
                        throw new \Exception("Jurnal INV {$invoice->invoice_number} tidak balance.");
                    }
                    if (!empty($jDetails)) JournalDetail::insert($jDetails);
                    $invoice->update(['journal_id' => $journalHeader->getKey()]);
                }

                $invCount++;
                if ($invCount % 50 === 0) {
                    $this->info("🔄 {$invCount} dokumen INV diproses...");
                }
            }

            DB::commit();
            $this->info("✅ SYNC INV SELESAI! Total {$invCount} dokumen INV berhasil di-import dengan stok Gudang terpotong (OUT).");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ SYNC GAGAL: " . $e->getMessage());
        }
    }
}

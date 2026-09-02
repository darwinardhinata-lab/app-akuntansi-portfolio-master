<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillDetail;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Support\NumberParser;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Services\InventorySyncService;

class FastImportBIL extends Command
{
    protected $signature = 'import:bil {file}';
    protected $description = 'Import Massal CSV Purchase Bill (BIL) via Terminal — otomatis update stok Gudang (IN)';

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

        // SMART DELIMITER DETECTOR
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        // Parse header
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
        $idxNoBukti = $findCol(['no bukti', 'no. bukti', 'bill', 'bil']);
        $idxSku     = $findCol(['sku', 'item code', 'kode barang']);
        $idxQty     = $findCol(['qty', 'quantity', 'jumlah']);
        $idxPrice   = $findCol(['harga', 'price', 'unit cost', 'hpp']);
        $idxVendor  = $findCol(['supplier', 'vendor', 'pemasok']);
        $idxDesc    = $findCol(['keterangan', 'deskripsi', 'description']);

        if ($idxDate == -1) $idxDate = 0;
        if ($idxNoBukti == -1) $idxNoBukti = 1;
        if ($idxSku == -1) $idxSku = 2;
        if ($idxQty == -1) $idxQty = 3;
        if ($idxPrice == -1) $idxPrice = 4;
        if ($idxVendor == -1) $idxVendor = 5;
        if ($idxDesc == -1) $idxDesc = 6;

        $this->info("🚀 Memulai import Purchase Bill (BIL → IN). Delimiter: '{$delimiter}'");

        // Preload products (SKU → id, stock_quantity, average_cost)
        $this->info("⏳ Memuat master produk (SKU) ke dalam RAM...");
        $products = DB::table('products')->pluck('id', 'sku')->toArray();

        // First pass: collect all rows grouped by document number
        $documents = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter, '"', '\\');
            }
            if (count($row) < 7) continue;

            $tanggal  = trim($row[$idxDate] ?? '');
            $noBukti  = trim($row[$idxNoBukti] ?? '');
            $sku      = trim($row[$idxSku] ?? '');
            $qty      = (int) preg_replace('/[^0-9\-]/', '', $row[$idxQty] ?? '0');
            $price    = NumberParser::parseDecimal($row[$idxPrice] ?? '0');
            $vendor   = trim($row[$idxVendor] ?? '');
            $desc     = trim($row[$idxDesc] ?? '');

            if (empty($noBukti) || strtolower($noBukti) === 'no bukti') continue;
            if (empty($sku) || $qty <= 0) continue;

            // Smart Date Parser
            $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des'], ['May', 'Oct', 'Aug', 'Dec'], $tanggal));
            $date = $time ? date('Y-m-d', $time) : date('Y-m-d');

            if (!isset($documents[$noBukti])) {
                $documents[$noBukti] = [
                    'bill_number'   => $noBukti,
                    'bill_date'     => $date,
                    'vendor_name'   => $vendor,
                    'items'         => [],
                ];
            }

            $documents[$noBukti]['items'][] = [
                'sku'       => $sku,
                'qty'       => $qty,
                'unit_cost' => $price,
                'description' => $desc,
            ];

            $count++;
        }
        fclose($handle);

        if (empty($documents)) {
            return $this->error("❌ Tidak ada data valid di dalam CSV.");
        }

        $this->info("📋 Ditemukan {$count} baris item dalam " . count($documents) . " dokumen BIL.");

        DB::beginTransaction();
        try {
            $bilCount = 0;
            foreach ($documents as $noBukti => $doc) {
                // Skip if bill already exists (idempotent)
                $existing = PurchaseBill::where('bill_number', $noBukti)->first();
                if ($existing) {
                    $this->warn("⚠️  BIL {$noBukti} sudah ada, dilewati.");
                    continue;
                }

                // Calculate totals
                $subTotal = 0;
                foreach ($doc['items'] as $item) {
                    $subTotal += $item['qty'] * $item['unit_cost'];
                }

                // Create Purchase Bill header
                $bill = PurchaseBill::create([
                    'bill_number'     => $doc['bill_number'],
                    'bill_date'       => $doc['bill_date'],
                    'vendor_name'     => $doc['vendor_name'] ?: 'Unknown Supplier',
                    'sub_total'       => $subTotal,
                    'tax_amount'      => 0,
                    'grand_total'     => $subTotal,
                    'credit_account'  => '22000', // Default Hutang Usaha
                    'payment_status'  => 'UNPAID',
                    'notes'           => 'Imported via CSV',
                ]);

                // Create Purchase Bill details
                $detailsToInsert = [];
                $invItems = [];
                foreach ($doc['items'] as $item) {
                    $productId = $products[$item['sku']] ?? null;
                    $lineTotal = $item['qty'] * $item['unit_cost'];

                    $detailsToInsert[] = [
                        'purchase_bill_id' => $bill->id,
                        'account_code'     => config('coa.persediaan'),
                        'description'      => $item['description'] ?: $item['sku'],
                        'amount'           => $lineTotal,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];

                    $invItems[] = [
                        'sku'       => $item['sku'],
                        'qty'       => $item['qty'],
                        'unit_cost' => $item['unit_cost'],
                    ];
                }
                PurchaseBillDetail::insert($detailsToInsert);

                // Create Journal (Debet: Persediaan, Kredit: Hutang)
                $journalHeader = JournalHeader::create([
                    'transaction_date' => $doc['bill_date'],
                    'evidence_number'  => $bill->bill_number,
                    'description'      => "Tagihan Pembelian: {$bill->vendor_name} (Imported)",
                    'source_doc_no'    => $bill->bill_number,
                    'transaction_type' => 'Purchase Bill',
                ]);

                $jDetails = [
                    ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'DEBET', 'amount' => $subTotal, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                    ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.hutang_usaha'), 'position' => 'KREDIT', 'amount' => $subTotal, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                ];

                if (!JournalBalanceValidator::isBalanced($jDetails)) {
                    throw new \Exception("Jurnal BIL {$bill->bill_number} tidak balance.");
                }
                JournalDetail::insert($jDetails);

                // Link journal_id ke bill
                $bill->update(['journal_id' => $journalHeader->getKey()]);

                // INTEGRASI GUDANG: BIL → IN (Stok Masuk)
                if (!empty($invItems)) {
                    $inventoryService = new InventorySyncService();
                    $inventoryService->processStockMovements(
                        $invItems,
                        $bill->bill_number,
                        $doc['bill_date'],
                        'BIL',
                        "Tagihan Pembelian: {$bill->vendor_name} ({$bill->bill_number})",
                        true
                    );
                }

                $bilCount++;
                if ($bilCount % 50 === 0) {
                    $this->info("🔄 {$bilCount} dokumen BIL diproses...");
                }
            }

            DB::commit();
            $this->info("✅ SYNC BIL SELESAI! Total {$bilCount} dokumen BIL berhasil di-import dengan stok Gudang terupdate (IN).");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ SYNC GAGAL: " . $e->getMessage());
        }
    }
}

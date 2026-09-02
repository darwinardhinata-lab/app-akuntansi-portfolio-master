<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Models\SalesInvoice;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Support\NumberParser;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Services\InventorySyncService;

class FastImportSR extends Command
{
    protected $signature = 'import:sr {file}';
    protected $description = 'Import Massal CSV Sales Return (SR) via Terminal — otomatis tambah stok Gudang (IN)';

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
        $idxNoRetur = $findCol(['no retur', 'return', 'sr']);
        $idxNoFaktur = $findCol(['no faktur', 'invoice', 'inv']);
        $idxSku     = $findCol(['sku', 'item code', 'kode barang']);
        $idxQty     = $findCol(['qty', 'quantity', 'jumlah']);
        $idxPrice   = $findCol(['harga', 'price', 'hpp']);
        $idxDesc    = $findCol(['keterangan', 'deskripsi', 'description']);

        if ($idxDate == -1) $idxDate = 0;
        if ($idxNoRetur == -1) $idxNoRetur = 1;
        if ($idxNoFaktur == -1) $idxNoFaktur = 2;
        if ($idxSku == -1) $idxSku = 3;
        if ($idxQty == -1) $idxQty = 4;
        if ($idxPrice == -1) $idxPrice = 5;
        if ($idxDesc == -1) $idxDesc = 6;

        $this->info("🚀 Memulai import Sales Return (SR → IN). Delimiter: '{$delimiter}'");

        $this->info("⏳ Memuat master produk (SKU) ke dalam RAM...");
        $products = DB::table('products')->pluck('id', 'sku')->toArray();

        $documents = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter, '"', '\\');
            }
            if (count($row) < 6) continue;

            $tanggal   = trim($row[$idxDate] ?? '');
            $noRetur   = trim($row[$idxNoRetur] ?? '');
            $noFaktur  = trim($row[$idxNoFaktur] ?? '');
            $sku       = trim($row[$idxSku] ?? '');
            $qty       = (int) preg_replace('/[^0-9\-]/', '', $row[$idxQty] ?? '0');
            $price     = NumberParser::parseDecimal($row[$idxPrice] ?? '0');
            $desc      = trim($row[$idxDesc] ?? '');

            if (empty($noRetur) || strtolower($noRetur) === 'no retur') continue;
            if (empty($sku) || $qty <= 0) continue;

            $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des'], ['May', 'Oct', 'Aug', 'Dec'], $tanggal));
            $date = $time ? date('Y-m-d', $time) : date('Y-m-d');

            if (!isset($documents[$noRetur])) {
                $documents[$noRetur] = [
                    'return_number'  => $noRetur,
                    'invoice_number' => $noFaktur,
                    'return_date'    => $date,
                    'items'          => [],
                ];
            }

            $documents[$noRetur]['items'][] = [
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

        $this->info("📋 Ditemukan {$count} baris item dalam " . count($documents) . " dokumen SR.");

        DB::beginTransaction();
        try {
            $srCount = 0;
            foreach ($documents as $noRetur => $doc) {
                $existing = SalesReturn::where('return_number', $noRetur)->first();
                if ($existing) {
                    $this->warn("⚠️  SR {$noRetur} sudah ada, dilewati.");
                    continue;
                }

                // Find the original invoice
                $invoice = SalesInvoice::where('invoice_number', $doc['invoice_number'])->first();
                $invoiceId = $invoice ? $invoice->id : null;

                $return = SalesReturn::create([
                    'return_number'     => $doc['return_number'],
                    'sales_invoice_id'  => $invoiceId,
                    'return_date'       => $doc['return_date'],
                    'status'            => 'COMPLETED',
                    'notes'             => 'Imported via CSV',
                ]);

                $detailsToInsert = [];
                $invItems = [];
                $totalCogsGood = 0;

                foreach ($doc['items'] as $item) {
                    $productId = $products[$item['sku']] ?? null;
                    $qty = $item['qty'];
                    $lineTotal = $qty * $item['unit_cost'];
                    $totalCogsGood += $lineTotal;

                    $detailsToInsert[] = [
                        'sales_return_id' => $return->id,
                        'product_id'      => $productId,
                        'item_code'       => $item['sku'],
                        'description'     => $item['description'] ?: $item['sku'],
                        'qty_returned'    => $qty,
                        'qty_approved'    => $qty,
                        'condition'       => 'GOOD',
                        'unit_price'      => $item['unit_cost'],
                        'cogs_value'      => $item['unit_cost'],
                        'subtotal_refund' => 0,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];

                    $invItems[] = [
                        'sku'       => $item['sku'],
                        'qty'       => $qty,
                        'unit_cost' => $item['unit_cost'],
                    ];
                }

                SalesReturnDetail::insert($detailsToInsert);

                // INTEGRASI GUDANG: SR → IN (Stok Masuk)
                if (!empty($invItems)) {
                    $inventoryService = new InventorySyncService();
                    $inventoryService->processStockMovements(
                        $invItems,
                        $return->return_number,
                        $doc['return_date'],
                        'SR',
                        "Retur Penjualan: {$return->return_number}",
                        true
                    );
                }

                // Create Journal (Debet: Persediaan, Kredit: HPP)
                if ($totalCogsGood > 0) {
                    $journalHeader = JournalHeader::create([
                        'transaction_date' => $doc['return_date'],
                        'evidence_number'  => $return->return_number,
                        'description'      => "Retur Penjualan: {$return->return_number} (Ref: {$doc['invoice_number']})",
                        'source_doc_no'    => $return->return_number,
                        'transaction_type' => 'Sales Return',
                    ]);

                    $jDetails = [
                        ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'DEBET', 'amount' => $totalCogsGood, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                        ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.hpp'), 'position' => 'KREDIT', 'amount' => $totalCogsGood, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                    ];

                    if (!JournalBalanceValidator::isBalanced($jDetails)) {
                        throw new \Exception("Jurnal SR {$return->return_number} tidak balance.");
                    }
                    JournalDetail::insert($jDetails);
                    $return->update(['journal_id' => $journalHeader->getKey()]);
                }

                $srCount++;
                if ($srCount % 50 === 0) {
                    $this->info("🔄 {$srCount} dokumen SR diproses...");
                }
            }

            DB::commit();
            $this->info("✅ SYNC SR SELESAI! Total {$srCount} dokumen SR berhasil di-import dengan stok Gudang bertambah (IN).");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ SYNC GAGAL: " . $e->getMessage());
        }
    }
}

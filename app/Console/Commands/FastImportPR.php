<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Modules\Platform\Support\OperationalCompany;
use App\Services\PurchaseReturnEligibility;
use App\Support\JournalBalanceValidator;
use App\Services\InventorySyncService;
use App\Services\PurchaseReturnValuationService;

class FastImportPR extends Command
{
    protected $signature = 'import:pr {file}';
    protected $description = 'Import Massal CSV Purchase Return (PR) via Terminal — otomatis potong stok Gudang (OUT)';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        DB::disableQueryLog();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("❌ File tidak ditemukan: {$filePath}");
            return self::FAILURE;
        }

        $handle = fopen($filePath, "r");
        if (!$handle) {
            $this->error("❌ Gagal membuka file CSV.");
            return self::FAILURE;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $this->error('Tidak ada data valid di dalam CSV.');
            return self::FAILURE;
        }
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
        $idxNoRetur = $findCol(['no retur', 'return', 'pr']);
        $idxNoPO    = $findCol(['no po', 'purchase order', 'po']);
        $idxSku     = $findCol(['sku', 'item code', 'kode barang']);
        $idxQty     = $findCol(['qty', 'quantity', 'jumlah']);
        $idxPrice   = $findCol(['harga', 'price', 'hpp']);
        $idxDesc    = $findCol(['keterangan', 'deskripsi', 'description']);

        if ($idxDate == -1) $idxDate = 0;
        if ($idxNoRetur == -1) $idxNoRetur = 1;
        if ($idxNoPO == -1) $idxNoPO = 2;
        if ($idxSku == -1) $idxSku = 3;
        if ($idxQty == -1) $idxQty = 4;
        if ($idxPrice == -1) $idxPrice = 5;
        if ($idxDesc == -1) $idxDesc = 6;

        $this->info("🚀 Memulai import Purchase Return (PR → OUT). Delimiter: '{$delimiter}'");

        $documents = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) === 1 && str_contains((string) $row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter, '"', '\\');
            }
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) continue;

            $tanggal  = trim($row[$idxDate] ?? '');
            $noRetur  = trim($row[$idxNoRetur] ?? '');
            $noPO     = trim($row[$idxNoPO] ?? '');
            $sku      = trim($row[$idxSku] ?? '');
            $qty      = filter_var(trim($row[$idxQty] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            $price    = trim($row[$idxPrice] ?? '');
            $desc     = trim($row[$idxDesc] ?? '');

            if ($noRetur === '' || mb_strlen($noRetur) > 100 || $noPO === '' || $sku === '' || $qty === false
                || !preg_match('/\A[0-9]{1,18}(?:\.[0-9]{1,2})?\z/', $price)
                || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $tanggal, $dateParts)
                || !checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])
                || (isset($documents[$noRetur]) && ($documents[$noRetur]['po_number'] !== $noPO
                    || $documents[$noRetur]['return_date'] !== $tanggal || isset($documents[$noRetur]['items'][$sku])))) {
                fclose($handle);
                $this->error('Data retur tidak valid pada record '.($count + 2).'.');
                return self::FAILURE;
            }
            $date = $tanggal;

            if (!isset($documents[$noRetur])) {
                $documents[$noRetur] = [
                    'return_number' => $noRetur,
                    'po_number'     => $noPO,
                    'return_date'   => $date,
                    'items'         => [],
                ];
            }

            $documents[$noRetur]['items'][$sku] = [
                'sku'       => $sku,
                'qty'       => $qty,
                'unit_cost' => bcadd($price, '0', 2),
                'description' => $desc,
            ];

            $count++;
        }
        fclose($handle);

        if (empty($documents)) {
            $this->error("❌ Tidak ada data valid di dalam CSV.");
            return self::FAILURE;
        }

        $this->info("📋 Ditemukan {$count} baris item dalam " . count($documents) . " dokumen PR.");

        DB::beginTransaction();
        try {
            $scope = app(OperationalCompany::class);
            if (!$scope->enabled() || !app()->runningInConsole() || request()->route() !== null) {
                throw new \RuntimeException('Import retur hanya tersedia pada CLI dengan ownership A2 aktif.');
            }
            $companyId = $scope->id();
            $eligibility = app(PurchaseReturnEligibility::class);
            \App\Modules\PaymentPlan\Services\LegacyFinancialFence::lock();
            \App\Support\AccountingPeriodGuard::source(array_column($documents, 'return_date'));
            $prCount = 0;
            foreach ($documents as $noRetur => $doc) {
                $po = $eligibility->lockOrder($companyId, $doc['po_number'], byNumber: true);
                ksort($doc['items'], SORT_STRING);
                $fingerprint = 'Imported via CSV: '.hash('sha256', json_encode($doc, JSON_THROW_ON_ERROR));
                $existing = PurchaseReturn::where('return_number', $noRetur)->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->purchase_order_id !== (int) $po->id || $existing->status !== 'COMPLETED'
                        || !hash_equals($fingerprint, (string) $existing->notes)) {
                        throw new \RuntimeException("Nomor retur {$noRetur} sudah ada dengan isi berbeda atau tanpa bukti impor.");
                    }
                    $this->warn("⚠️  PR {$noRetur} sudah ada, dilewati.");
                    continue;
                }

                $poId = $po->id;

                $return = PurchaseReturn::create([
                    'return_number'       => $doc['return_number'],
                    'purchase_order_id'   => $poId,
                    'return_date'         => $doc['return_date'],
                    'status'              => 'COMPLETED',
                    'notes'               => $fingerprint,
                ]);

                $detailsToInsert = [];
                $invItems = [];
                $totalReturnAmount = '0.00';

                foreach ($doc['items'] as $item) {
                    $lines = $po->details()->where('item_code', $item['sku'])->limit(2)->lockForUpdate()->get();
                    $line = $lines->first();
                    $product = $line?->product_id ? Product::whereKey($line->product_id)->lockForUpdate()->first() : null;
                    if ($lines->count() !== 1 || !$product || $line->item_code !== $item['sku'] || $product->sku !== $item['sku']
                        || bccomp((string) $line->price, $item['unit_cost'], 2) !== 0) {
                        throw new \RuntimeException("Baris SKU {$item['sku']} tidak cocok dengan detail PO.");
                    }
                    $qty = $item['qty'];
                    $eligibility->assertLine($po, $line, $qty);
                    $lineTotal = bcmul((string) $qty, (string) $line->price, 2);
                    $totalReturnAmount = bcadd($totalReturnAmount, $lineTotal, 2);

                    $detailsToInsert[] = [
                        'purchase_return_id' => $return->id,
                        'product_id'         => $product->id,
                        'item_code'          => $item['sku'],
                        'description'        => $line->description,
                        'qty_returned'       => $qty,
                        'price'              => $item['unit_cost'],
                        'subtotal'           => $lineTotal,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];

                    $invItems[] = [
                        'sku'       => $item['sku'],
                        'qty'       => $qty,
                        'unit_cost' => $product->average_cost,
                    ];
                }

                if (bccomp($totalReturnAmount, '0', 2) <= 0) {
                    throw new \RuntimeException(__('erp.audit_return_guard'));
                }
                PurchaseReturnDetail::insert($detailsToInsert);

                // INTEGRASI GUDANG: PR → OUT (Stok Keluar)
                if (!empty($invItems)) {
                    $inventoryService = new InventorySyncService();
                    $inventoryResult = $inventoryService->processStockMovements(
                        $invItems,
                        $return->return_number,
                        $doc['return_date'],
                        'PR',
                        "Retur Pembelian: {$return->return_number} (Ref PO: {$doc['po_number']})",
                        true
                    );
                }

                // Create Journal (Debet: Hutang, Kredit: Persediaan)
                $journalHeader = JournalHeader::create([
                    'transaction_date' => $doc['return_date'],
                    'evidence_number'  => $return->return_number,
                    'description'      => "Retur Pembelian (Debit Note) Ref PO: {$doc['po_number']}",
                    'source_doc_no'    => $return->return_number,
                    'transaction_type' => 'Purchase Return',
                ]);

                $noTransaksiPP = str_replace('PO-', '', $po->po_number);
                $paymentPlan = DB::table('transaksi_payment_plan')->where('no_transaksi', $noTransaksiPP)
                    ->where('kategori_payment', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')->first();
                $akunHutang = $paymentPlan ? config('coa.uang_muka_beli') : config('coa.hutang_usaha');
                $jDetails = app(PurchaseReturnValuationService::class)->journalDetails(
                    $journalHeader->getKey(), $akunHutang, $totalReturnAmount, $inventoryResult['cogs_value']
                );

                if (!JournalBalanceValidator::isBalanced($jDetails)) {
                    throw new \Exception("Jurnal PR {$return->return_number} tidak balance.");
                }
                JournalDetail::insert($jDetails);
                // Same linkage as controller: journal_headers.source_doc_no = return_number.
                $return->update(['total_return_amount' => $totalReturnAmount]);

                $prCount++;
                if ($prCount % 50 === 0) {
                    $this->info("🔄 {$prCount} dokumen PR diproses...");
                }
            }

            DB::commit();
            $this->info("✅ SYNC PR SELESAI! Total {$prCount} dokumen PR berhasil di-import dengan stok Gudang terpotong (OUT).");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("❌ SYNC GAGAL: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}

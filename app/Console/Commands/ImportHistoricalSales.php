<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Product;
use App\Services\SalesOrderService;
use App\Support\NumberParser;

class ImportHistoricalSales extends Command
{
    protected $signature = 'import:sales {file} {--skip-journal : Abaikan posting ke Jurnal Akuntansi}';
    protected $description = 'Import & Sinkronisasi Big Data Penjualan (Ratusan Ribu Baris) secara aman';

    public function handle(SalesOrderService $soService)
    {
        ini_set('memory_limit', '1024M');
        DB::disableQueryLog();

        $fileName = $this->argument('file');
        $skipJournal = $this->option('skip-journal');

        // DETEKSI PATH OTOMATIS: 
        // Cek apakah user mengetik path lengkap (Drive D:\...) atau file ada di folder root
        if (file_exists($fileName)) {
            $filePath = $fileName;
        } else {
            // Jika hanya ketik nama file, otomatis cari di folder storage/app/
            $filePath = storage_path('app/' . $fileName);
        }

        if (!file_exists($filePath)) {
            $this->error("GAGAL: File tidak ditemukan baik di root folder maupun di {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Menganalisa File: {$fileName} ...");
        
        $handle = fopen($filePath, 'r');
        
        // =========================================================
        // 1. DYNAMIC HEADER HUNTER
        // =========================================================
        $headerRowIndex = -1;
        $headerCols = [];
        $delimiter = ',';
        $currentLine = 0;

        while (($lineStr = fgets($handle)) !== false && $currentLine < 50) {
            $cleanLine = preg_replace('/^\xEF\xBB\xBF/', '', $lineStr);
            if (trim(str_replace([';', ',', '"', "'"], '', $cleanLine)) === '') {
                $currentLine++;
                continue;
            }

            $countSemi = substr_count($cleanLine, ';');
            $countComma = substr_count($cleanLine, ',');
            $tempDelimiter = $countSemi > $countComma ? ';' : ',';

            $cols = str_getcsv(strtolower($cleanLine), $tempDelimiter, '"', '\\');
            $cols = array_map('trim', $cols);
            
            $score = 0;
            $rowStr = implode(' ', $cols);
            
            if (stripos($rowStr, 'pesanan') !== false || stripos($rowStr, 'salesorder_no') !== false) $score++;
            if (stripos($rowStr, 'tanggal') !== false || stripos($rowStr, 'date') !== false) $score++;
            if (stripos($rowStr, 'pelanggan') !== false || stripos($rowStr, 'customer') !== false) $score++;
            if (stripos($rowStr, 'qty') !== false || stripos($rowStr, 'jumlah') !== false) $score++;
            if (stripos($rowStr, 'harga') !== false || stripos($rowStr, 'price') !== false) $score++;
            if (stripos($rowStr, 'sku') !== false) $score++;

            if ($score >= 3) {
                $headerRowIndex = $currentLine;
                $headerCols = $cols;
                $delimiter = $tempDelimiter;
                break;
            }
            $currentLine++;
        }

        if ($headerRowIndex === -1) {
            $this->error('Gagal mendeteksi Judul Kolom (Header). Pastikan CSV tidak rusak.');
            fclose($handle);
            return Command::FAILURE;
        }

        // =========================================================
        // 2. MAPPING KOLOM DINAMIS
        // =========================================================
        $findCol = function($keywords) use ($headerCols) {
            foreach ($headerCols as $idx => $colName) {
                foreach ($keywords as $key) {
                    if (trim($colName) == $key || stripos($colName, $key) !== false) return $idx;
                }
            }
            return -1; 
        };

        $idxTgl      = $findCol(['tanggal', 'transaction_date']);
        $idxSo       = $findCol(['no pesanan', 'no. pesanan', 'pesanan', 'salesorder_no']);
        $idxCustomer = $findCol(['pelanggan', 'customer_name', 'penerima']);
        $idxSku      = $findCol(['sku', 'item_code', 'kode barang']);
        $idxDesc     = $findCol(['nama barang', 'description', 'deskripsi']);
        $idxPrice    = $findCol(['harga jual', 'harga', 'price']);
        $idxQty      = $findCol(['qty', 'kuantitas', 'jumlah']);
        $idxDisc     = $findCol(['diskon', 'disc']);
        $idxTax      = $findCol(['pajak', 'tax']);
        $idxAmount   = $findCol(['amount', 'total', 'jumlah harga']);
        $idxStatus   = $findCol(['status', 'internal_status']);
        $idxLokasi   = $findCol(['nama toko', 'lokasi', 'location_name', 'gudang']);

        // AUTO DETECT: Membutuhkan QTY, No Pesanan, dan minimal salah satu antara (Harga Jual ATAU Amount)
        if ($idxSo === -1 || $idxQty === -1 || ($idxPrice === -1 && $idxAmount === -1)) {
            $this->error("Gagal: Kolom Wajib (No Pesanan, QTY, dan Harga Jual / Amount) tidak ditemukan!");
            fclose($handle);
            return Command::FAILURE;
        }

        $products = Product::pluck('id', 'sku')->toArray();
        rewind($handle);
        for ($i = 0; $i <= $headerRowIndex; $i++) { fgets($handle); }

        $soHeaders = []; 
        $soDetails = [];
        $autoShipList = []; 
        $countLines = 0;
        
        $stats = ['baru' => 0, 'revisi' => 0, 'skip' => 0];

        $this->info("Memulai Ekstraksi & Sinkronisasi Baris Data...");
        $bar = $this->output->createProgressBar();
        $bar->start();

        DB::beginTransaction();

        try {
        // =========================================================
        // 3. STREAMING & SINKRONISASI BACA BIG DATA
        // =========================================================
        while (($r = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== FALSE) {
            if (!isset($r[$idxSo])) continue;

            $soNumber = trim($r[$idxSo]);
            if (empty($soNumber) || strtolower($soNumber) == 'no pesanan') continue;

            $qtyRaw = $r[$idxQty] ?? '0';
            if (preg_replace('/[^0-9]/', '', $qtyRaw) === '' || preg_replace('/[^0-9]/', '', $qtyRaw) == '0') continue; 

            // 🚨 LOGIKA SINKRONISASI (ANTI DOBEL JURNAL) 🚨
            if (!isset($soHeaders[$soNumber])) {
                $existingSo = SalesOrder::where('so_number', $soNumber)->first();

                // PROTEKSI MUTLAK: Jika SO sudah jadi Invoice, lompati total seluruh barisnya!
                if ($existingSo && $existingSo->status === 'SHIPPED') {
                    $soHeaders[$soNumber] = 'SKIPPED';
                    $stats['skip']++;
                    continue; 
                }

                $tglRaw   = $idxTgl >= 0 ? trim($r[$idxTgl]) : '';
                $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des', '/'], ['May', 'Oct', 'Aug', 'Dec', '-'], $tglRaw));
                $tanggal = $time ? date('Y-m-d', $time) : date('Y-m-d');
                
                $rawStatus = $idxStatus >= 0 ? strtolower(trim($r[$idxStatus])) : 'selesai';
                $isCompleted = in_array($rawStatus, ['selesai', 'completed', 'shipped', 'lunas']);

                $customer = $idxCustomer >= 0 ? trim($r[$idxCustomer] ?? '') : 'Pelanggan Umum';
                $lokasi   = $idxLokasi >= 0 ? trim($r[$idxLokasi] ?? '') : 'Pusat';
                if (empty($customer)) $customer = 'Pelanggan Umum';

                if (!$existingSo) {
                    // CREATE: Data Belum Ada
                    $so = SalesOrder::create([
                        'so_number'        => $soNumber,
                        'transaction_date' => $tanggal,
                        'contact_name'     => $customer,
                        'location_name'    => $lokasi,
                        'sub_total'        => 0, 
                        'disc_amount'      => 0,
                        'tax_amount'       => 0,
                        'grand_total'      => 0, 
                        'status'           => 'APPROVED', 
                        
                        // 👇 INJEKSI KODE JUBELIO DI SINI 👇
                        'store_name'       => $lokasi, 
                        'source_name'      => 'Jubelio CSV Import',
                        // 👆 ---------------------------- 👆
                    ]);
                    $stats['baru']++;
                } else {
                    // UPDATE: Data sudah ada tapi masih Draft/Pending
                    $so = $existingSo;
                    $so->update([
                        'transaction_date' => $tanggal,
                        'contact_name'     => $customer,
                        'location_name'    => $lokasi,
                        'status'           => 'APPROVED', 

                        // 👇 INJEKSI KODE JUBELIO DI SINI 👇
                        'store_name'       => $lokasi,
                        // 👆 ---------------------------- 👆
                    ]);
                    $stats['revisi']++;
                }

                $soHeaders[$soNumber] = $so->id;
                
                if ($isCompleted) {
                    $autoShipList[$soNumber] = $so->id;
                }
                
                // Bersihkan detail lama (jika direvisi), agar diganti dengan detail CSV terbaru
                SalesOrderDetail::where('sales_order_id', $so->id)->delete();

            } elseif ($soHeaders[$soNumber] === 'SKIPPED') {
                // Jika Header-nya tadi diputuskan kena skip, maka lewati juga baris Rincian ini
                continue;
            }

            // FIX: DRY — gunakan NumberParser helper (mengganti closure $cleanNum)
            $qty        = (int)NumberParser::parseDecimal($r[$idxQty] ?? '0');
            $disc       = $idxDisc >= 0 ? NumberParser::parseDecimal($r[$idxDisc] ?? '0') : 0;
            $tax        = $idxTax >= 0 ? NumberParser::parseDecimal($r[$idxTax] ?? '0') : 0;
            
            // Tangkap nilai total (Amount)
            $amount     = $idxAmount >= 0 ? NumberParser::parseDecimal($r[$idxAmount] ?? '0') : 0;
            
            // SMART DETECT MODE:
            // Jika ada kolom Harga Jual (Mode Pesanan), ambil harga aslinya.
            // Jika tidak ada (Mode Faktur), hitung harga satuan = Amount dibagi QTY.
            if ($idxPrice >= 0) {
                $price = NumberParser::parseDecimal($r[$idxPrice] ?? '0');
            } else {
                $price = $qty > 0 ? ($amount / $qty) : 0;
            }

            // Jika keadaannya terbalik (ada Harga Jual tapi tidak ada Amount)
            if ($idxAmount === -1) {
                $amount = $price * $qty;
            }
            $itemCode   = $idxSku >= 0 ? trim($r[$idxSku] ?? '') : 'PRODUK-UMUM';
            $desc       = $idxDesc >= 0 ? trim($r[$idxDesc] ?? '') : '-';

            $productId = $products[$itemCode] ?? null;

            $soDetails[] = [
                'sales_order_id' => $soHeaders[$soNumber],
                'product_id'     => $productId,
                'item_code'      => $itemCode,
                'description'    => $desc,
                'price'          => $price,
                'qty'            => $qty,
                'qty_shipped'    => 0,
                'disc_amount'    => $disc,
                'tax_amount'     => $tax,
                'amount'         => $amount,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
            $countLines++;

            // Chunk Insert per 2500 baris agar RAM ringan dan cepat
            if (count($soDetails) >= 2500) {
                SalesOrderDetail::insert($soDetails);
                $soDetails = [];
            }
            $bar->advance();
        }

        fclose($handle);

        if (!empty($soDetails)) {
            SalesOrderDetail::insert($soDetails);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Menghitung ulang Grand Total SO secara massal...");

        // Hapus SO Number yang statusnya 'SKIPPED' dari array sebelum update
        $validSoHeaders = array_filter($soHeaders, function($id) { return $id !== 'SKIPPED'; });

        // =========================================================================
            // OPTIMASI: Update Total Kalkulasi SO secara massal (Batch Update via CASE WHEN)
            // =========================================================================
            
            // 1. Ambil semua ID SO yang valid (Buang yang statusnya 'SKIPPED')
            $soIds = array_values(array_filter($soHeaders, fn($id) => $id !== 'SKIPPED'));

            if (!empty($soIds)) {
                // 2. Single aggregation query (Satu kali tarik total dari seluruh SO)
                $totals = DB::table('sales_order_details')
                    ->whereIn('sales_order_id', $soIds)
                    ->groupBy('sales_order_id')
                    ->pluck(DB::raw('SUM(amount)'), 'sales_order_id');

                // 3. Batch UPDATE via CASE WHEN dalam 1 statement per chunk
                foreach (array_chunk($soIds, 500) as $chunkIds) {
                    $cases = '';
                    foreach ($chunkIds as $soId) {
                        $total = $totals[$soId] ?? 0;
                        $cases .= "WHEN {$soId} THEN {$total} ";
                    }
                    $inClause = implode(',', $chunkIds);
                    
                    DB::statement("
                        UPDATE sales_orders 
                        SET sub_total = CASE id {$cases} END, 
                            grand_total = CASE id {$cases} END 
                        WHERE id IN ({$inClause})
                    ");
                }
            }

        DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            $this->error("Error baris " . $countLines . ": " . $e->getMessage());
            return Command::FAILURE;
        }

        // =========================================================
        // 4. MESIN GENERATE FAKTUR & JURNAL OTOMATIS
        // =========================================================
        $this->info("Menyiapkan Faktur dan " . ($skipJournal ? "Pemotongan Stok Tanpa Jurnal..." : "Pemotongan Stok & Penjurnalan..."));
        
        $autoJournalCount = 0;
        $barFaktur = $this->output->createProgressBar(count($autoShipList));
        $barFaktur->start();

        if (count($autoShipList) > 0) {
            foreach ($autoShipList as $soNumber => $soId) {
                $so = SalesOrder::with('details')->find($soId);
                
                if ($so && $so->status === 'APPROVED') {
                    $itemsToShip = [];
                    foreach ($so->details as $det) {
                        $itemsToShip[] = ['item_code' => $det->item_code, 'qty' => $det->qty, 'price' => $det->price, 'is_substitution' => false];
                    }

                    $financials = [
                        'sub_total'         => $so->sub_total,
                        'disc_amount'       => $so->disc_amount + $so->other_discount,
                        'tax_amount'        => $so->tax_amount,
                        'shipping_cost'     => $so->shipping_cost,
                        'shipping_discount' => $so->shipping_discount,
                        'other_cost'        => $so->other_cost,
                        'return_remaining'  => $so->return_remaining,
                        'grand_total'       => $so->grand_total,
                    ];
                    
                    try {
                        $soService->createInvoiceAndShip($so->id, $so->transaction_date, $itemsToShip, $financials, $skipJournal);
                        $autoJournalCount++;
                    } catch (\Exception $e) {}
                }
                $barFaktur->advance();
            }
        }

        $barFaktur->finish();
        $this->newLine(2);

        $this->line("<bg=green;fg=white;options=bold> DONE! IMPOR & SINKRONISASI SELESAI </>");
        $this->line("Rincian Operasi Transaksi:");
        $this->info("✔ [+] Pesanan Baru Dibuat    : {$stats['baru']} Dokumen SO");
        $this->info("✔ [~] Pesanan Draft Direvisi : {$stats['revisi']} Dokumen SO");
        $this->line("✔ [>] <fg=yellow>Dilewati (Sudah Difaktur)  : {$stats['skip']} Dokumen SO (Aman dari Jurnal Ganda)</>");
        $this->newLine();
        $this->info("✔ Total Rincian Produk       : {$countLines} baris item");
        $this->info("✔ Faktur Diterbitkan         : {$autoJournalCount} Dokumen");
        
        return Command::SUCCESS;
    }
}
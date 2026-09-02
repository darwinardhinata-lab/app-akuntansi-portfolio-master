<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;
use App\Support\NumberParser;

class FastImportPO extends Command
{
    protected $signature = 'import:po {file}';
    protected $description = 'Import Massal CSV Detail Purchase Order via Terminal (Aman dari RAM Limit)';

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

        // SMART DELIMITER DETECTOR: Deteksi apakah CSV pakai koma (,) atau titik koma (;)
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);
        
        // Skip header
        fgetcsv($handle, 0, $delimiter, '"', '\\');

        $this->info("⏳ Memuat master produk (SKU) ke dalam RAM...");
        $products = DB::table('products')->pluck('id', 'sku')->toArray();

        $this->info("🚀 Memulai proses baca & insert baris PO (Delimiter terdeteksi: '{$delimiter}')...");
        
        DB::beginTransaction();
        
        try {
            $poHeaders = []; 
            $poDetails = [];
            $count = 0;
            $now = now();

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                // Perbaiki anomali jika Excel membungkus 1 baris full dengan tanda kutip
                if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                    $row = str_getcsv($row[0], $delimiter, '"', '\\');
                }

                if (count($row) < 12) continue;

                $tglRaw   = trim($row[0] ?? '');
                $poNumber = trim($row[1] ?? '');
                $itemCode = trim($row[2] ?? '');

                if (empty($poNumber) || strtolower($poNumber) == 'purchase order no.') continue;

                // Smart Date Parser Jubelio ('25 Mei 2026' -> 2026-05-25)
                $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des'], ['May', 'Oct', 'Aug', 'Dec'], $tglRaw));
                $tanggal = $time ? date('Y-m-d', $time) : date('Y-m-d');

                // Generate / Cek Header
                if (!isset($poHeaders[$poNumber])) {
                    $po = PurchaseOrder::updateOrCreate(
                        ['po_number' => $poNumber],
                        [
                            'transaction_date' => $tanggal,
                            'contact_name'     => trim($row[4] ?? ''),
                            'location_name'    => trim($row[12] ?? ''),
                            'sub_total'        => NumberParser::parseDecimal($row[10] ?? '0'),
                            'grand_total'      => NumberParser::parseDecimal($row[11] ?? '0'),
                            'status'           => 'APPROVED',
                        ]
                    );
                    $poHeaders[$poNumber] = $po->id;
                    
                    // Hapus detail lama agar tidak ganda saat sinkronisasi ulang
                    DB::table('purchase_order_details')->where('purchase_order_id', $po->id)->delete();
                }

                $productId = $products[$itemCode] ?? null;

                $poDetails[] = [
                    'purchase_order_id' => $poHeaders[$poNumber],
                    'product_id'        => $productId,
                    'item_code'         => mb_substr($itemCode, 0, 255),
                    'description'       => mb_substr(trim($row[3] ?? ''), 0, 255),
                    'price'             => NumberParser::parseDecimal($row[5] ?? '0'),
                    'qty'               => (int) preg_replace('/[^0-9\-]/', '', $row[6] ?? '0'),
                    'qty_received'      => 0,
                    'disc_amount'       => NumberParser::parseDecimal($row[7] ?? '0'),
                    'tax_amount'        => NumberParser::parseDecimal($row[8] ?? '0'),
                    'amount'            => NumberParser::parseDecimal($row[9] ?? '0'),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                $count++;

                // CHUNK SIZE DITURUNKAN KE 500 (Mencegah error 'Too many placeholders')
                if (count($poDetails) >= 500) {
                    DB::table('purchase_order_details')->insert($poDetails);
                    $poDetails = [];
                    $this->info("🔄 {$count} baris detail PO diproses...");
                }
            }

            if (count($poDetails) > 0) {
                DB::table('purchase_order_details')->insert($poDetails);
            }

            DB::commit();
            fclose($handle);
            
            $this->info("✅ SYNC PO SELESAI! Total {$count} baris detail pesanan terekam.");

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("❌ SYNC GAGAL: " . $e->getMessage());
        }
    }
}
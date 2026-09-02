<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Support\NumberParser;

class FastImportProduct extends Command
{
    protected $signature = 'import:product {file}';
    protected $description = 'Import 200k+ Master Barang CSV via Terminal (Smart Multiline Parser)';

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

        // Skip header
        fgetcsv($handle, 0, $delimiter, '"', '\\');

        $this->info("🚀 Memulai proses baca & insert 200.000+ Produk (Delimiter: '{$delimiter}'). Harap bersabar...");
        
        $batch = [];
        $count = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter, '"', '\\');
            }

            if (count($row) < 17) continue;

            $sku  = trim($row[3] ?? '');
            $name = trim($row[2] ?? '');

            if (empty($sku) || strtolower($sku) === 'sku') continue;

            $sellPrice = NumberParser::parseDecimal($row[11] ?? '0');
            $stock = preg_replace('/[^0-9\-]/', '', $row[17] ?? '0');

            $batch[] = [
                'sku'            => mb_substr($sku, 0, 255),
                'name'           => mb_substr($name, 0, 255),
                'category_name'  => mb_substr(trim($row[4] ?? ''), 0, 255),
                'variation'      => mb_substr(trim($row[5] ?? ''), 0, 255),
                'sell_price'     => $sellPrice,
                'stock_quantity' => (int) $stock,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            // CHUNK SIZE DITURUNKAN KE 500
            if (count($batch) >= 500) {
                DB::table('products')->upsert(
                    $batch, 
                    ['sku'], 
                    ['name', 'category_name', 'variation', 'sell_price', 'stock_quantity', 'updated_at']
                );
                
                $count += count($batch);
                $this->info("🔄 {$count} Produk berhasil disinkronisasi...");
                $batch = []; 
            }
        }

        if (count($batch) > 0) {
            DB::table('products')->upsert(
                $batch, 
                ['sku'], 
                ['name', 'category_name', 'variation', 'sell_price', 'stock_quantity', 'updated_at']
            );
            $count += count($batch);
        }

        fclose($handle);
        $this->info("✅ SYNC SELESAI! Total {$count} SKU Barang berhasil di-import/di-update.");
    }
}
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProductDashboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Timeout 1 jam jika dataset besar
    protected $forceFullSync;

    /**
     * Create a new job instance.
     *
     * @param bool $forceFullSync Set true jika ingin sinkronisasi ulang seluruh data tanpa filter update_at
     */
    public function __construct(bool $forceFullSync = false)
    {
        $this->forceFullSync = $forceFullSync;
    }

    public function handle()
    {
        Log::info('SyncProductDashboardJob started. ForceFullSync: ' . ($this->forceFullSync ? 'YES' : 'NO'));

        try {
            $now = now();
            $totalSynced = 0;

            // 1. Ambil timestamp update terakhir dari tabel akuntansi (products) untuk incremental sync
            $lastSync = null;
            if (!$this->forceFullSync) {
                $lastSync = DB::table('products')->max('updated_at');
            }

            // 2. Query dasar dari database dashboard
            $query = DB::connection('dashboard')->table('products');

            // Cek secara aman apakah tabel item_categories tersedia di DB dashboard
            $hasCategoryTable = false;
            try {
                $hasCategoryTable = DB::connection('dashboard')->getSchemaBuilder()->hasTable('item_categories');
            } catch (\Throwable $e) {
                $hasCategoryTable = false;
            }

            if ($hasCategoryTable) {
                $query->leftJoin('item_categories', 'products.item_category_id', '=', 'item_categories.item_category_id')
                    ->select('products.*', 'item_categories.name as category_name_from_db');
            } else {
                $query->select('products.*');
            }

            // Incremental sync berdasarkan last_modified atau created_date
            if ($lastSync) {
                $query->where(function ($q) use ($lastSync) {
                    $q->where('products.last_modified', '>=', $lastSync)
                      ->orWhere('products.created_date', '>=', $lastSync);
                });
                Log::info('Incremental sync aktif. Menyaring produk dengan last_modified/created_date >= ' . $lastSync);
            }

            // 3. Proses sync per-chunk (500 baris data per iterasi)
            $query->chunkById(500, function ($products) use ($now, &$totalSynced) {
                    $batch = [];

                    foreach ($products as $item) {
                        // Memastikan SKU tidak kosong dan sesuai batas 100 karakter
                        $sku = trim($item->item_code ?? '');
                        if (empty($sku)) {
                            $sku = 'ITEM-' . $item->item_id;
                        }
                        $sku = mb_substr($sku, 0, 100);

                        // Ambil timestamp update/create dari dashboard
                        $updatedAt = $item->last_modified ?? $item->created_date ?? $now;
                        $createdAt = $item->created_date ?? $now;

                        // Ambil nama kategori jika ada
                        $categoryName = $item->category_name_from_db ?? null;

                        $batch[] = [
                            'item_id'                => $item->item_id,
                            'item_group_id'          => $item->item_group_id,
                            'sku'                    => $sku,
                            'name'                   => mb_substr(trim($item->item_name ?? 'Tanpa Nama'), 0, 255),
                            'variation'              => $item->variation_values ? mb_substr(trim($item->variation_values), 0, 255) : null,
                            'category_name'          => $categoryName ? mb_substr(trim($categoryName), 0, 255) : null,
                            'unit'                   => 'Pcs',
                            'sell_price'             => (float) ($item->sell_price ?? 0),
                            'average_cost'           => (float) ($item->buy_price ?? 0),
                            'stock_quantity'         => 0, // Nilai awal persediaan
                            'inventory_account_code' => '11200',
                            'cogs_account_code'      => '55000',
                            'created_at'             => $createdAt,
                            'updated_at'             => $updatedAt,
                        ];
                    }

                    if (!empty($batch)) {
                        // Direct Upsert ke tabel bersamaw_akuntasi.products
                        DB::table('products')->upsert(
                            $batch,
                            ['sku'], // Unique Key yang dicocokkan
                            [
                                'item_id',
                                'item_group_id',
                                'name',
                                'variation',
                                'category_name',
                                'sell_price',
                                'average_cost',
                                'updated_at',
                            ]
                        );

                        $totalSynced += count($batch);
                    }

                    // Jeda 0.1 detik agar beban server tetap stabil
                    usleep(100000);
                }, 'products.item_id', 'item_id');

            Log::info("SyncProductDashboardJob selesai! Total product disinkronkan: {$totalSynced}");

        } catch (\Exception $e) {
            Log::error('SyncProductDashboardJob gagal: ' . $e->getMessage());
            throw $e;
        }
    }
}

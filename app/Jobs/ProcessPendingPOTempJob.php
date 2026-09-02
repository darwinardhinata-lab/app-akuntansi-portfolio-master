<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Product;

class ProcessPendingPOTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function handle()
    {
        Log::info('ProcessPendingPOTempJob started.');

        try {
            // Cache product IDs berdasarkan sku/item_code untuk efisiensi
            $productsCache = Product::pluck('id', 'sku')->toArray();
            $now = now();

            // Proses menggunakan chunk agar tidak "MySQL server has gone away" / Memory Limit
            DB::table('po_temp')
                ->where('status_sync', 'PENDING')
                ->orderBy('purchaseorder_id')
                ->chunk(100, function ($pendingPOs) use ($productsCache, $now) {

                    if ($pendingPOs->isEmpty()) {
                        return;
                    }

                    $poTempIds = $pendingPOs->pluck('purchaseorder_id')->toArray();

                    // Ambil detail sekaligus untuk 1 chunk ini
                    $allDetails = DB::table('pod_temp')
                        ->whereIn('purchaseorder_id', $poTempIds)
                        ->get()
                        ->groupBy('purchaseorder_id');

                    DB::beginTransaction();
                    try {
                        foreach ($pendingPOs as $tempPo) {
                            $po = PurchaseOrder::updateOrCreate(
                                ['po_number' => $tempPo->purchaseorder_no],
                                [
                                    'transaction_date' => $tempPo->transaction_date ? date('Y-m-d', strtotime($tempPo->transaction_date)) : now()->toDateString(),
                                    'contact_name' => $tempPo->supplier_name ?: 'Supplier Umum',
                                    'location_name' => $tempPo->location_name,
                                    'status' => $tempPo->status ?? 'APPROVED',
                                    'sub_total' => $tempPo->sub_total ?? 0,
                                    'grand_total' => $tempPo->grand_total ?? 0,
                                    'is_include_ppn' => $tempPo->is_tax_included ? 1 : 0,
                                    'tax_addition_amount' => $tempPo->total_tax ?? 0,
                                    'tax_deduction_amount' => 0,
                                ]
                            );

                            PurchaseOrderDetail::where('purchase_order_id', $po->id)->delete();

                            $tempDetails = $allDetails->get($tempPo->purchaseorder_id, []);
                            $detailsToInsert = [];

                            foreach ($tempDetails as $det) {
                                $productId = $productsCache[$det->item_code] ?? null;
                                $detailsToInsert[] = [
                                    'purchase_order_id' => $po->id,
                                    'product_id' => $productId,
                                    'item_code' => $det->item_code,
                                    'description' => $det->description,
                                    'price' => $det->price ?? 0,
                                    'qty' => $det->qty ?? 0,
                                    'qty_received' => 0, // Inisial default belum diterima
                                    'disc_amount' => $det->disc_amount ?? 0,
                                    'tax_amount' => $det->tax_amount ?? 0,
                                    'amount' => $det->amount ?? 0,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }

                            if (!empty($detailsToInsert)) {
                                PurchaseOrderDetail::insert($detailsToInsert);
                            }
                        }

                        // Update status chunk ini jadi SUCCESS
                        DB::table('po_temp')
                            ->whereIn('purchaseorder_id', $poTempIds)
                            ->update([
                                'status_sync' => 'SUCCESS',
                                'updated_at' => $now,
                            ]);

                        DB::commit();
                        Log::info('Successfully processed a chunk of ' . count($poTempIds) . ' pending POs.');
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Chunk failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
                        throw $e;
                    }
                });

            Log::info('ProcessPendingPOTempJob completed successfully.');

        } catch (\Exception $e) {
            Log::error('ProcessPendingPOTempJob overall failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
            throw $e;
        }
    }
}

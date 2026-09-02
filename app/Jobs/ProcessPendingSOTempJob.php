<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Product;

class ProcessPendingSOTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function handle()
    {
        Log::info('ProcessPendingSOTempJob started.');

        try {
            $now = now();

            // Proses menggunakan chunk agar tidak "MySQL server has gone away" / Memory Limit
            DB::table('so_temp')
                ->where('status_sync', 'PENDING')
                ->orderBy('salesorder_id')
                ->chunk(100, function ($pendingSOs) use ($now) {

                    if ($pendingSOs->isEmpty()) {
                        return;
                    }

                    $soTempIds = $pendingSOs->pluck('salesorder_id')->toArray();

                    // Ambil detail sekaligus untuk 1 chunk ini
                    $allDetails = DB::table('sod_temp')
                        ->whereIn('salesorder_id', $soTempIds)
                        ->get();

                    // Cache product IDs untuk SKU yang ada dalam chunk ini saja (menghemat memori)
                    $itemCodes = collect($allDetails)->pluck('item_code')->unique()->toArray();
                    $productsCache = Product::whereIn('sku', $itemCodes)->pluck('id', 'sku')->toArray();

                    $allDetails = collect($allDetails)->groupBy('salesorder_id');

                    DB::beginTransaction();
                    try {
                        foreach ($pendingSOs as $tempSo) {
                            $weightGram = isset($tempSo->total_weight_in_kg) ? ($tempSo->total_weight_in_kg * 1000) : 0;
                            $sourceName = !empty($tempSo->source_name) ? $tempSo->source_name : (!empty($tempSo->source) ? $tempSo->source : 'JUBELIO');

                            $so = SalesOrder::updateOrCreate(
                                ['so_number' => $tempSo->salesorder_no],
                                [
                                    'transaction_date' => $tempSo->transaction_date ? date('Y-m-d', strtotime($tempSo->transaction_date)) : now()->toDateString(),
                                    'invoice_id' => $tempSo->invoice_id ?? null,
                                    'invoice_no' => $tempSo->invoice_no ?? null,
                                    'contact_name' => $tempSo->customer_name ?: 'Pelanggan Umum',
                                    'ref_number' => $tempSo->ref_no,
                                    'salesman' => $tempSo->salesmen_name ?? null,
                                    'source' => $sourceName,
                                    'store_name' => $tempSo->store_name,
                                    'location_name' => $tempSo->location_name,
                                    'remarks' => $tempSo->note,
                                    'is_tax_included' => $tempSo->is_tax_included ? 1 : 0,
                                    'receiver_name' => $tempSo->shipping_full_name,
                                    'receiver_address' => $tempSo->shipping_address,
                                    'receiver_phone' => $tempSo->shipping_phone,
                                    'is_cod' => $tempSo->is_cod ? 1 : 0,
                                    'tracking_number' => $tempSo->tracking_number,
                                    'total_weight' => $weightGram,
                                    'is_jubelio_shipment' => $tempSo->is_jubelio_shipment ? 1 : 0,
                                    'courier' => $tempSo->courier,
                                    'status' => 'APPROVED', // Status akuntansi mutlak
                                    'wms_status' => $tempSo->wms_status ?? null, // Status operasional gudang Jubelio
                                    'is_paid' => $tempSo->is_paid ? 1 : 0,

                                    'sub_total' => $tempSo->sub_total ?? 0,
                                    'disc_amount' => $tempSo->total_disc ?? 0,
                                    'other_discount' => $tempSo->discount_marketplace ?? 0,
                                    'tax_amount' => $tempSo->total_tax ?? 0,
                                    'shipping_cost' => $tempSo->shipping_cost ?? 0,
                                    'shipping_discount' => $tempSo->shipping_cost_discount ?? 0,
                                    'other_cost' => $tempSo->add_cost ?? 0,
                                    'return_remaining' => 0,
                                    'grand_total' => $tempSo->grand_total ?? 0,
                                ]
                            );

                            SalesOrderDetail::where('sales_order_id', $so->id)->delete();

                            $tempDetails = $allDetails->get($tempSo->salesorder_id, []);
                            $detailsToInsert = [];

                            foreach ($tempDetails as $det) {
                                $productId = $productsCache[$det->item_code] ?? null;
                                $detailsToInsert[] = [
                                    'sales_order_id' => $so->id,
                                    'product_id' => $productId,
                                    'item_code' => $det->item_code,
                                    'description' => $det->description,
                                    'price' => $det->price ?? 0,
                                    'qty' => $det->qty ?? 0,
                                    'qty_shipped' => 0,
                                    'disc_amount' => $det->disc_amount ?? 0,
                                    'tax_amount' => $det->tax_amount ?? 0,
                                    'amount' => $det->amount ?? 0,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }

                            if (!empty($detailsToInsert)) {
                                SalesOrderDetail::insert($detailsToInsert);
                            }
                        }

                        // Update status chunk ini jadi SUCCESS
                        DB::table('so_temp')
                            ->whereIn('salesorder_id', $soTempIds)
                            ->update([
                                'status_sync' => 'SUCCESS',
                                'updated_at' => $now,
                            ]);

                        DB::commit();
                        Log::info('Successfully processed a chunk of ' . count($soTempIds) . ' pending SOs.');
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error('Chunk failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
                        throw $e;
                    }
                });

            Log::info('ProcessPendingSOTempJob completed successfully.');

        } catch (\Throwable $e) {
            Log::error('ProcessPendingSOTempJob overall failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
            throw $e;
        }
    }
}

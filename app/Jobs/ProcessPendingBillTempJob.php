<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillDetail;
use App\Models\PurchaseOrder;
use App\Models\Product;

class ProcessPendingBillTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function handle()
    {
        Log::info('ProcessPendingBillTempJob started.');

        try {
            // Cache product IDs berdasarkan sku/item_code untuk efisiensi
            $productsCache = Product::pluck('id', 'sku')->toArray();
            
            // Cache PO IDs berdasarkan po_number
            $poCache = PurchaseOrder::pluck('id', 'po_number')->toArray();
            
            $now = now();

            // Proses menggunakan chunk agar tidak "MySQL server has gone away" / Memory Limit
            DB::table('bill_temp')
                ->where('status_sync', 'PENDING')
                ->orderBy('bill_id')
                ->chunk(100, function ($pendingBills) use ($productsCache, $poCache, $now) {

                    if ($pendingBills->isEmpty()) {
                        return;
                    }

                    $billTempIds = $pendingBills->pluck('bill_id')->toArray();

                    // Ambil detail sekaligus untuk 1 chunk ini
                    $allDetails = DB::table('billd_temp')
                        ->whereIn('bill_id', $billTempIds)
                        ->get()
                        ->groupBy('bill_id');

                    DB::beginTransaction();
                    try {
                        foreach ($pendingBills as $tempBill) {
                            
                            $poId = null;
                            if (!empty($tempBill->purchaseorder_no)) {
                                $poId = $poCache[$tempBill->purchaseorder_no] ?? null;
                            }
                            
                            // Logika status payment
                            $paymentStatus = 'UNPAID';
                            if (($tempBill->payment ?? 0) > 0 && ($tempBill->payment ?? 0) >= ($tempBill->grand_total ?? 0)) {
                                $paymentStatus = 'PAID';
                            } elseif (($tempBill->payment ?? 0) > 0) {
                                $paymentStatus = 'PARTIAL';
                            }

                            $bill = PurchaseBill::updateOrCreate(
                                ['bill_number' => $tempBill->bill_no],
                                [
                                    'purchase_order_id' => $poId,
                                    'transaction_date' => $tempBill->transaction_date ? date('Y-m-d', strtotime($tempBill->transaction_date)) : now()->toDateString(),
                                    'contact_name' => $tempBill->supplier_name ?: 'Supplier Umum',
                                    'sub_total' => $tempBill->sub_total ?? 0,
                                    'disc_amount' => $tempBill->total_disc ?? 0,
                                    'tax_amount' => $tempBill->total_tax ?? 0,
                                    'shipping_cost' => $tempBill->add_cost ?? 0, // Mapping add_cost ke shipping_cost
                                    'grand_total' => $tempBill->grand_total ?? 0,
                                    'payment_status' => $paymentStatus,
                                ]
                            );

                            PurchaseBillDetail::where('purchase_bill_id', $bill->id)->delete();

                            $tempDetails = $allDetails->get($tempBill->bill_id, []);
                            $detailsToInsert = [];

                            foreach ($tempDetails as $det) {
                                $productId = $productsCache[$det->item_code] ?? null;
                                $detailsToInsert[] = [
                                    'purchase_bill_id' => $bill->id,
                                    'product_id' => $productId,
                                    'item_code' => $det->item_code,
                                    'description' => $det->description,
                                    'price' => $det->price ?? 0,
                                    'qty' => $det->qty ?? 0,
                                    'disc_amount' => $det->disc_amount ?? 0,
                                    'amount' => $det->amount ?? 0,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }

                            if (!empty($detailsToInsert)) {
                                PurchaseBillDetail::insert($detailsToInsert);
                            }
                        }

                        // Update status chunk ini jadi SUCCESS
                        DB::table('bill_temp')
                            ->whereIn('bill_id', $billTempIds)
                            ->update([
                                'status_sync' => 'SUCCESS',
                                'updated_at' => $now,
                            ]);

                        DB::commit();
                        Log::info('Successfully processed a chunk of ' . count($billTempIds) . ' pending Bills.');
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Chunk failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
                        throw $e;
                    }
                });

            Log::info('ProcessPendingBillTempJob completed successfully.');

        } catch (\Exception $e) {
            Log::error('ProcessPendingBillTempJob overall failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
            throw $e;
        }
    }
}

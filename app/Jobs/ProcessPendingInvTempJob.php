<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\SalesOrder;
use App\Models\Product;

class ProcessPendingInvTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function handle()
    {
        Log::info('ProcessPendingInvTempJob started.');

        try {
            // Cache product IDs berdasarkan sku untuk efisiensi
            $productsCache = Product::pluck('id', 'sku')->toArray();

            // Cache Sales Order ID berdasarkan so_number
            $salesOrdersCache = SalesOrder::pluck('id', 'so_number')->toArray();

            $now = now();

            // Proses menggunakan chunk agar tidak "MySQL server has gone away" / Memory Limit
            DB::table('inv_temp')
                ->where('status_sync', 'PENDING')
                ->orderBy('invoice_id')
                ->chunk(90, function ($pendingInvs) use ($productsCache, $salesOrdersCache, $now) {

                    if ($pendingInvs->isEmpty()) {
                        return;
                    }

                    $invTempIds = $pendingInvs->pluck('invoice_id')->toArray();

                    // Ambil detail sekaligus untuk 1 chunk ini
                    $allDetails = DB::table('invd_temp')
                        ->whereIn('invoice_id', $invTempIds)
                        ->get()
                        ->groupBy('invoice_id');

                    DB::beginTransaction();
                    try {
                        foreach ($pendingInvs as $tempInv) {

                            $soId = $salesOrdersCache[$tempInv->salesorder_no] ?? null;

                            // Update or Create Sales Invoice Header
                            $inv = SalesInvoice::updateOrCreate(
                                ['invoice_number' => $tempInv->invoice_no],
                                [
                                    'sales_order_id' => $soId,
                                    'transaction_date' => $tempInv->transaction_date ? date('Y-m-d', strtotime($tempInv->transaction_date)) : now()->toDateString(),
                                    'contact_name' => $tempInv->customer_name ?: 'Pelanggan Umum',
                                    'sub_total' => $tempInv->sub_total ?? 0,
                                    'disc_amount' => $tempInv->total_disc ?? 0,
                                    'tax_amount' => $tempInv->total_tax ?? 0,
                                    'shipping_cost' => $tempInv->shipping_cost ?? 0,
                                    'grand_total' => $tempInv->grand_total ?? 0,
                                    'payment_status' => ($tempInv->payment_amount >= $tempInv->grand_total && $tempInv->grand_total > 0) ? 'PAID' : 'UNPAID',
                                ]
                            );

                            // Hapus detail lama jika update (agar tidak dobel)
                            SalesInvoiceDetail::where('sales_invoice_id', $inv->id)->delete();

                            $tempDetails = $allDetails->get($tempInv->invoice_id, []);
                            $detailsToInsert = [];

                            foreach ($tempDetails as $det) {
                                $productId = $productsCache[$det->item_code] ?? null;

                                $detailsToInsert[] = [
                                    'sales_invoice_id' => $inv->id,
                                    'product_id' => $productId,
                                    'item_code' => $det->item_code,
                                    'description' => $det->description,
                                    'price' => $det->price ?? 0,
                                    'qty_actual' => $det->qty ?? 0,
                                    'disc_amount' => $det->disc_amount ?? 0,
                                    'amount' => $det->amount ?? 0,
                                    'is_substitution' => 0, // default
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }

                            if (!empty($detailsToInsert)) {
                                SalesInvoiceDetail::insert($detailsToInsert);
                            }
                        }

                        // Update status chunk ini jadi SUCCESS
                        DB::table('inv_temp')
                            ->whereIn('invoice_id', $invTempIds)
                            ->update([
                                'status_sync' => 'SUCCESS'
                            ]);

                        DB::commit();
                        Log::info('Successfully processed a chunk of ' . count($invTempIds) . ' pending Invoices.');
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error('Chunk failed (Invoice): ' . $e->getMessage() . ' di baris ' . $e->getLine());
                        throw $e;
                    }
                });

            Log::info('ProcessPendingInvTempJob completed successfully.');

        } catch (\Throwable $e) {
            Log::error('ProcessPendingInvTempJob overall failed: ' . $e->getMessage() . ' di baris ' . $e->getLine());
            throw $e;
        }
    }
}

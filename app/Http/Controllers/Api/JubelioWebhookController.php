<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Product;
use App\Services\SalesOrderService;

class JubelioWebhookController extends Controller
{
    /**
     * Endpoint untuk menerima kiriman data Penjualan (JSON) dari Jubelio
     */
    public function handleSalesWebhook(Request $request, SalesOrderService $soService)
    {
        // 1. Ambil seluruh data JSON dari body request
        $payload = $request->all();

        // Antisipasi jika Jubelio membungkus datanya di dalam array "data"
        $headerData = $payload['data'][0] ?? $payload;

        // Ambil Nomor SO sebagai identitas utama
        $soNumber = $headerData['salesorder_no'] ?? null;
        if (!$soNumber) {
            return response()->json(['status' => 'error', 'message' => 'Format JSON tidak valid, salesorder_no tidak ditemukan'], 400);
        }

        DB::beginTransaction();
        try {
            // 2. Proteksi Anti-Ganda (Sama seperti di CSV)
            $existingSo = SalesOrder::where('so_number', $soNumber)->first();
            if ($existingSo && $existingSo->status === 'SHIPPED') {
                return response()->json(['status' => 'success', 'message' => "SO {$soNumber} sudah diproses (SHIPPED) sebelumnya. Dilewati."], 200);
            }

            // 3. Ekstraksi Data Finansial & Toko dari JSON
            $tanggal      = date('Y-m-d H:i:s', strtotime($headerData['transaction_date'] ?? now()));
            $customer     = $headerData['customer_name'] ?? 'Pelanggan Umum';
            $lokasi       = $headerData['location_name'] ?? 'Pusat';
            $storeName    = $headerData['store_name'] ?? $headerData['channel_name'] ?? $lokasi;
            
            $subTotal         = floatval($headerData['sub_total'] ?? 0);
            $discAmount       = floatval($headerData['total_disc'] ?? 0);
            $taxAmount        = floatval($headerData['total_tax'] ?? 0);
            $grandTotal       = floatval($headerData['grand_total'] ?? 0);
            $shippingCost     = floatval($headerData['shipping_cost'] ?? 0);
            $shippingDiscount = floatval($headerData['shipping_discount'] ?? 0);
            $otherCost        = floatval($headerData['other_cost'] ?? 0);
            $returnRemaining  = floatval($headerData['return_remaining'] ?? 0);

            // 4. Simpan ke Tabel Sales Order (Header)
            if (!$existingSo) {
                $so = SalesOrder::create([
                    'so_number'        => $soNumber,
                    'transaction_date' => $tanggal,
                    'contact_name'     => $customer,
                    'location_name'    => $lokasi,
                    'sub_total'        => $subTotal,
                    'disc_amount'      => $discAmount,
                    'other_discount'   => $headerData['discount_marketplace'] ?? 0, // Diskon marketplace dari Jubelio
                    'tax_amount'       => $taxAmount,
                    'shipping_cost'    => $shippingCost,
                    'shipping_discount'=> $shippingDiscount ?? 0, // Diskon ongkir dari Jubelio
                    'grand_total'      => $grandTotal,
                    'status'           => 'APPROVED', // Status akuntansi mutlak
                    'wms_status'       => $headerData['wms_status'] ?? null, // Status operasional gudang Jubelio
                    'store_name'       => $storeName,
                    'source_name'      => 'Jubelio Webhook API',
                ]);
            } else {
                $so = $existingSo;
                $so->update([
                    'transaction_date' => $tanggal,
                    'contact_name'     => $customer,
                    'location_name'    => $lokasi,
                    'sub_total'        => $subTotal,
                    'disc_amount'      => $discAmount,
                    'other_discount'   => $headerData['discount_marketplace'] ?? 0, // Diskon marketplace
                    'tax_amount'       => $taxAmount,
                    'shipping_cost'    => $shippingCost,
                    'shipping_discount'=> $shippingDiscount ?? 0, // Diskon ongkir
                    'grand_total'      => $grandTotal,
                    'status'           => 'APPROVED', // Status akuntansi mutlak
                    'wms_status'       => $headerData['wms_status'] ?? null, // Status operasional gudang Jubelio
                    'store_name'       => $storeName,
                ]);
                // Bersihkan detail lama untuk diganti yang baru dari JSON
                SalesOrderDetail::where('sales_order_id', $so->id)->delete();
            }

            // 5. Ekstraksi Detail Item (Array)
            // Di JSON Jubelio biasanya ada di dalam key "items"
            $items = $headerData['items'] ?? $payload['items'] ?? [];
            $itemsToShip = [];

            foreach ($items as $item) {
                $itemCode = $item['item_code'] ?? 'UMUM';
                $qty      = floatval($item['qty'] ?? 0);
                $price    = floatval($item['price'] ?? ($item['sell_price'] ?? 0));
                $amount   = floatval($item['amount'] ?? 0);
                $disc     = floatval($item['disc_amount'] ?? 0);

                // Tarik relasi produk dari Master Produk di Laravel Anda
                $product = Product::where('sku', $itemCode)->first();

                SalesOrderDetail::create([
                    'sales_order_id' => $so->id,
                    'product_id'     => $product ? $product->id : null,
                    'item_code'      => $itemCode,
                    'description'    => $item['item_name'] ?? '-',
                    'price'          => $price,
                    'qty'            => $qty,
                    'qty_shipped'    => 0,
                    'disc_amount'    => $disc,
                    'tax_amount'     => 0,
                    'amount'         => $amount,
                ]);

                // Siapkan keranjang array untuk dieksekusi pemotongan stok
                $itemsToShip[] = [
                    'item_code' => $itemCode,
                    'qty'       => $qty,
                    'price'     => $price,
                    'is_substitution' => false
                ];
            }

            // --- LOGIKA PENANGANAN RETUR (SALES RETURN) ---
            $internalStatus = strtoupper($headerData['internal_status'] ?? '');

            if (in_array($internalStatus, ['RETURNED', 'RETURN_REQUESTED'])) {
                $existingInvoice = \App\Models\SalesInvoice::whereHas('salesOrder', function($q) use ($soNumber) {
                    $q->where('so_number', $soNumber);
                })->first();

                // FIX: Ubah SO status menjadi RETURN_PENDING agar user ERP tahu ada retur masuk
                if ($existingSo) {
                    $existingSo->update(['status' => 'RETURN_PENDING']);
                }

                if (!$existingInvoice) {
                    return response()->json(['status' => 'error', 'message' => "Faktur Penjualan untuk SO {$soNumber} tidak ditemukan. Retur tidak bisa diproses."], 404);
                }

                // Cegah duplikasi pembuatan dokumen retur
                $existingReturn = \App\Models\SalesReturn::where('sales_invoice_id', $existingInvoice->id)->first();
                if ($existingReturn) {
                     return response()->json(['status' => 'success', 'message' => "Dokumen retur untuk faktur {$existingInvoice->invoice_number} sudah ada di sistem ERP."], 200);
                }

                // Buat Dokumen Retur (Staging Gudang)
                // P2-2: Gunakan DocumentSequence (row-lock) untuk menghindari race condition
                $returnNumber = \App\Support\DocumentSequence::generateSecure(
                    'sales_returns', 'return_number', 'RET-' . $soNumber . '-'
                );
                $returnDoc = \App\Models\SalesReturn::create([
                    'return_number' => $returnNumber,
                    'sales_invoice_id' => $existingInvoice->id,
                    'return_date' => $tanggal,
                    'status' => 'PENDING_INSPECTION',
                ]);

                // Looping detail barang yang diretur
                foreach ($items as $item) {
                     // Cari harga jual asli dari detail faktur untuk mengunci nilai piutang
                     $invDet = \App\Models\SalesInvoiceDetail::where('sales_invoice_id', $existingInvoice->id)
                                    ->where('item_code', $item['item_code'] ?? '')->first();

                     \App\Models\SalesReturnDetail::create([
                         'sales_return_id' => $returnDoc->id,
                         'product_id'      => $invDet ? $invDet->product_id : null,
                         'item_code'       => $item['item_code'] ?? 'UMUM',
                         'description'     => $item['item_name'] ?? '-',
                         'qty_returned'    => floatval($item['qty'] ?? 0),
                         'unit_price'      => $invDet ? $invDet->price : 0,
                     ]);
                }

                DB::commit();
                return response()->json(['status' => 'success', 'message' => "Dokumen Retur {$returnDoc->return_number} berhasil diterbitkan dan menunggu pemeriksaan tim gudang."], 200);
            }
            // --- AKHIR LOGIKA RETUR ---

            // 6. EKSEKUSI MESIN FAKTUR & JURNAL OTOMATIS
            // Pemicu: Jika JSON dari Jubelio menyatakan 'COMPLETED' atau 'is_paid' true
            
            // FIX: Hanya tembak Jurnal JIKA status benar-benar SHIPPED/COMPLETED.
            // Jangan tembak jika hanya is_paid true tapi barang belum dikirim (kecuali sistem Anda PO Pre-order).
            if (in_array($internalStatus, ['SHIPPED', 'COMPLETED'])) {
                $financials = [
                    'sub_total'         => $subTotal,
                    'disc_amount'       => $discAmount,
                    'tax_amount'        => $taxAmount,
                    'shipping_cost'     => $shippingCost,
                    'shipping_discount' => $shippingDiscount,
                    'other_cost'        => $otherCost,
                    'return_remaining'  => $returnRemaining,
                    'grand_total'       => $grandTotal,
                ];

                // FIX ANTI-DOBEL: Ambil nomor invoice asli dari Jubelio jika ada di payload.
                // Ini menyatukan sumber kebenaran nomor faktur antara webhook dan import:inv,
                // sehingga import:inv otomatis idempoten terhadap data yang sudah masuk lewat webhook.
                $jubelioInvoiceNo = $headerData['invoice_no'] ?? $headerData['invoice_number'] ?? null;

                // Memanggil Service sakti Anda untuk potong stok dan cetak jurnal!
                $soService->createInvoiceAndShip($so->id, $tanggal, $itemsToShip, $financials, false, $jubelioInvoiceNo);
            }

            DB::commit();
            return response()->json([
                'status'  => 'success', 
                'message' => "Sales Order {$soNumber} berhasil ditangkap dan dieksekusi menjadi Jurnal!"
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error', 
                'message' => 'Gagal memproses data: ' . $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\Product;
use App\Models\InventoryLedger;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\SystemLog;
use App\Services\InventorySyncService;

class SalesInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        // 1. QUERY STANDARD LIST (Untuk Tab Daftar Faktur - Paginated)
        $invoicesQuery = SalesInvoice::with(['salesOrder'])
            ->when($search, function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%");
            })
            ->when($start_date && $end_date, function($q) use ($start_date, $end_date) {
                $q->whereBetween('transaction_date', [$start_date, $end_date]);
            })
            ->orderBy('transaction_date', 'desc');

        // --- CATCH EXPORT REQUEST ---
        if ($request->get('export') === 'excel') {
            $exportQuery = SalesInvoice::with(['salesOrder'])
                ->when($search, function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('contact_name', 'like', "%{$search}%");
                })
                ->when($start_date && $end_date, function($q) use ($start_date, $end_date) {
                    $q->whereBetween('transaction_date', [$start_date, $end_date]);
                })
                ->orderBy('transaction_date', 'desc');

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SalesInvoiceExport($exportQuery), 
                'Faktur_Penjualan_' . date('Ymd_His') . '.xlsx'
            );
        }

        $invoices = $invoicesQuery->paginate(50)
            ->appends($request->query());

        // ======================================================================
        // 2. BIG DATA PIVOT ENGINE (Aggregasi multidimensi langsung dari MySQL)
        // ======================================================================
        // Mengompres 200.000 baris menjadi ringkasan dimensi agar browser tidak hang
        $pivotQuery = DB::table('sales_invoices as si')
            ->join('sales_invoice_details as sid', 'si.id', '=', 'sid.sales_invoice_id')
            ->leftJoin('sales_orders as so', 'si.sales_order_id', '=', 'so.id')
            ->select([
                DB::raw("STRFTIME('%Y-%m', si.transaction_date) as bulan"),
                DB::raw("IFNULL(so.location_name, 'Pusat') as lokasi"),
                'si.contact_name as pelanggan',
                'sid.item_code as sku',
                DB::raw("SUM(sid.qty_actual) as total_qty"),
                DB::raw("CAST(SUM(sid.amount) AS INTEGER) as total_omset")
            ]);

        // Berikan filter tanggal yang sama pada menu analitik pivotnya
        if ($start_date && $end_date) {
            $pivotQuery->whereBetween('si.transaction_date', [$start_date, $end_date]);
        }

        $analyticData = $pivotQuery->groupBy('bulan', 'lokasi', 'pelanggan', 'sku')
            ->get();

        return view('sales_invoice.index', compact('invoices', 'analyticData', 'search', 'start_date', 'end_date'));
    }

    public function dispatchSyncJob()
    {
        \App\Jobs\SyncInvDashboardToTempJob::dispatch();
        return response()->json(['success' => true, 'message' => 'Proses Sync Invoice (Dashboard -> Temp) sedang berjalan di background.']);
    }

    public function create()
    {
        $products = Product::orderBy('name', 'asc')->get();
        $invoicePrefix = 'INV-' . date('ymd') . '-';
        $lastInv = DB::table('sales_invoices')->where('invoice_number', 'like', $invoicePrefix . '%')->orderByDesc('invoice_number')->value('invoice_number');
        $seq = $lastInv ? ((int) substr($lastInv, strlen($invoicePrefix)) + 1) : 1;
        $autoNumber = $invoicePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        return view('sales_invoice.create', compact('products', 'autoNumber'));
    }

    public function store(Request $request)
    {
        // FIX: Tambahkan proteksi agar qty dan price tidak boleh bernilai negatif atau nol
        $request->validate([
            'invoice_number'   => 'required|unique:sales_invoices,invoice_number',
            'transaction_date' => 'required|date',
            'contact_name'     => 'required|string',
            'details'          => 'required|array|min:1',
            'details.*.qty'    => 'required|numeric|min:1',
            'details.*.price'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;
            $totalDiscItems = 0;

            foreach ($request->details as $det) {
                if (isset($det['qty']) && $det['qty'] > 0) {
                    $barisTotal = ($det['price'] * $det['qty']);
                    $subTotal += $barisTotal;
                    $totalDiscItems += ($det['disc_amount'] ?? 0);
                }
            }

            $otherDisc = $request->other_discount ?? 0;
            $taxAmount = $request->tax_amount ?? 0;
            $shippingCost = $request->shipping_cost ?? 0;
            $shippingDisc = $request->shipping_discount ?? 0;
            $otherCost = $request->other_cost ?? 0;

            $grandTotal = ($subTotal - $totalDiscItems - $otherDisc) + $taxAmount + ($shippingCost - $shippingDisc) + $otherCost;

            $invoice = SalesInvoice::create([
                'invoice_number'   => $request->invoice_number,
                'sales_order_id'   => null, // Null karena ini direct invoice
                'transaction_date' => $request->transaction_date,
                'contact_name'     => $request->contact_name,
                'sub_total'        => $subTotal,
                'disc_amount'      => $totalDiscItems + $otherDisc,
                'tax_amount'       => $taxAmount,
                'shipping_cost'    => $shippingCost,
                'grand_total'      => $grandTotal,
                'payment_status'   => $request->is_paid ? 'PAID' : 'UNPAID',
            ]);

            $totalCogsValue = 0;
            $invoiceDetails = [];
            $now = now();

            // ============================================================
            // INTEGRASI GUDANG (WAREHOUSE): INV → OUT (Stok Keluar)
            // ============================================================
            // FIX: Ganti loop N+1 (Product::where('sku') per item) dengan
            // InventorySyncService yang preload semua produk sekaligus.
            $items = [];
            foreach ($request->details as $det) {
                if (isset($det['qty']) && $det['qty'] > 0) {
                    $items[] = [
                        'sku'       => $det['item_code'],
                        'qty'       => (int) $det['qty'],
                        'unit_cost' => 0, // Service akan pakai average_cost produk
                    ];
                }
            }

            // Gunakan InventorySyncService untuk stock movements (INV → OUT)
            $inventoryService = new InventorySyncService();
            $invResult = $inventoryService->processStockMovements(
                $items,
                $invoice->invoice_number,
                $request->transaction_date,
                'INV',
                "Faktur Penjualan Manual: {$invoice->invoice_number}",
                true
            );

            $totalCogsValue = $invResult['cogs_value'];

            // Query products sekali saja untuk invoice details (no N+1)
            $productSkus = array_column($items, 'sku');
            $products = !empty($productSkus)
                ? Product::whereIn('sku', $productSkus)->get()->keyBy('sku')
                : collect();

            foreach ($request->details as $det) {
                if (isset($det['qty']) && $det['qty'] > 0) {
                    $qty = (int) $det['qty'];
                    $product = $products->get($det['item_code']);

                    $invoiceDetails[] = [
                        'sales_invoice_id' => $invoice->id,
                        'product_id'       => $product ? $product->id : null,
                        'item_code'        => $det['item_code'],
                        'description'      => $det['description'] ?? ($product->name ?? '-'),
                        'price'            => $det['price'],
                        'qty_actual'       => $qty,
                        'disc_amount'      => $det['disc_amount'] ?? 0,
                        'amount'           => ($det['price'] * $qty) - ($det['disc_amount'] ?? 0),
                        'is_substitution'  => false,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }

            if (!empty($invoiceDetails)) SalesInvoiceDetail::insert($invoiceDetails);

            // Jurnal Akuntansi Otomatis
            if ($grandTotal > 0 || $totalCogsValue > 0) {
                $journalHeader = JournalHeader::create([
                    'transaction_date' => $request->transaction_date,
                    'evidence_number'  => $invoice->invoice_number,
                    'description'      => "Faktur Penjualan Langsung: {$invoice->invoice_number} - Pelanggan: {$request->contact_name}",
                    'source_doc_no'    => $invoice->invoice_number,
                    'transaction_type' => 'Faktur',
                ]);

                $primaryId = $journalHeader->getKey();
                $jDetails = [];

                if ($grandTotal > 0) {
                    $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.piutang_usaha'), 'position' => 'DEBET', 'amount' => $grandTotal, 'created_at' => $now, 'updated_at' => $now];
                    
                    if ($totalDiscItems > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_penjualan'), 'position' => 'DEBET', 'amount' => $totalDiscItems, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($shippingDisc > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_ongkir'), 'position' => 'DEBET', 'amount' => $shippingDisc, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($otherDisc > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.diskon_lain'), 'position' => 'DEBET', 'amount' => $otherDisc, 'created_at' => $now, 'updated_at' => $now];
                    }
                    
                    $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.penjualan'), 'position' => 'KREDIT', 'amount' => $subTotal, 'created_at' => $now, 'updated_at' => $now];
                    
                    if ($shippingCost > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.ongkos_kirim'), 'position' => 'KREDIT', 'amount' => $shippingCost, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($taxAmount > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.pajak_keluaran'), 'position' => 'KREDIT', 'amount' => $taxAmount, 'created_at' => $now, 'updated_at' => $now];
                    }
                    if ($otherCost > 0) {
                        $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.biaya_lain'), 'position' => 'KREDIT', 'amount' => $otherCost, 'created_at' => $now, 'updated_at' => $now];
                    }
                }

                if ($totalCogsValue > 0) {
                    $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.hpp'), 'position' => 'DEBET', 'amount' => $totalCogsValue, 'created_at' => $now, 'updated_at' => $now];
                    $jDetails[] = ['journal_id' => $primaryId, 'account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => $totalCogsValue, 'created_at' => $now, 'updated_at' => $now];
                }

                if (!empty($jDetails)) JournalDetail::insert($jDetails);
            }

            DB::commit();
            SystemLog::record('CREATE', 'Sales Invoice', 'Membuat faktur penjualan manual: ' . $invoice->invoice_number);
            return redirect()->route('invoice.index')->with('success', 'Faktur Penjualan Manual berhasil diterbitkan. Stok Gudang dan Jurnal Keuangan otomatis diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal memproses faktur: ' . $e->getMessage());
        }
    }

    public function show($id) {
        $invoice = SalesInvoice::with(['details.product', 'salesOrder'])->findOrFail($id);
        return view('sales_invoice.show', compact('invoice'));
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $invoice = SalesInvoice::findOrFail($id);

            // 1. Hapus Jurnal Akuntansi yang tercipta dari Faktur Ini
            $journalIds = JournalHeader::where('evidence_number', $invoice->invoice_number)->pluck('journal_id');
            if ($journalIds->isNotEmpty()) {
                JournalDetail::whereIn('journal_id', $journalIds)->delete();
                JournalHeader::whereIn('journal_id', $journalIds)->delete();
            }

            // 2. Kembalikan Stok Barang ke Gudang & Hapus Riwayat Kartu Stok
            $stockChanges = DB::table('inventory_ledgers')
                ->where('evidence_number', $invoice->invoice_number)
                ->where('type', 'OUT')
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(qty) as total_qty'))
                ->pluck('total_qty', 'product_id');

            foreach ($stockChanges as $productId => $qty) {
                Product::where('id', $productId)->increment('stock_quantity', (int)$qty);
            }
            InventoryLedger::where('evidence_number', $invoice->invoice_number)->delete();

            // 3. Jika Faktur berasal dari SO, ubah status SO kembali ke APPROVED agar bisa diproses ulang
            if ($invoice->sales_order_id) {
                DB::table('sales_orders')->where('id', $invoice->sales_order_id)->update(['status' => 'APPROVED']);
            }

            // 4. Hapus Detail & Header Invoice
            SalesInvoiceDetail::where('sales_invoice_id', $invoice->id)->delete();
            $invoice->delete();

            DB::commit();
            SystemLog::record('DELETE', 'Sales Invoice', 'Membatalkan dan menghapus faktur: ' . $invoice->invoice_number);
            
            return redirect()->route('invoice.index')->with('success', 'Faktur berhasil dihapus. Stok telah dikembalikan ke Gudang dan Jurnal Keuangan otomatis dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus faktur: ' . $e->getMessage());
        }
    }

}

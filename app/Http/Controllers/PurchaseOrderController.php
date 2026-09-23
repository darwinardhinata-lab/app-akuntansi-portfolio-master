<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Product;
use App\Models\SystemLog;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\DB;
use App\Models\Tax;
use App\Support\NumberParser;

class PurchaseOrderController extends Controller
{
    protected $poService;

    // <-- TAMBAHAN BARU: Constructor untuk Injeksi Dependency
    public function __construct(PurchaseOrderService $poService)
    {
        $this->poService = $poService;
    }

    public function dispatchSyncJob()
    {
        try {
            // Dispatch tahap 1
            \App\Jobs\SyncPODashboardToTempJob::dispatch();
            
            return back()->with('success', 'Proses sinkronisasi PO dari Dashboard sedang berjalan di background (Tahap 1 & 2).');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Gagal dispatch PO Sync: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memulai sinkronisasi: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $status = $request->get('status');
        
        $ordersQuery = PurchaseOrder::with('details')
            ->when($search, function($q) use ($search) {
                $q->where(function($query) use ($search) {
                    $query->where('po_number', 'like', "%{$search}%")
                          ->orWhere('contact_name', 'like', "%{$search}%");
                });
            })
            ->when($start_date && $end_date, function($q) use ($start_date, $end_date) {
                $q->whereBetween('transaction_date', [$start_date, $end_date]);
            })
            ->when($status, function($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy('transaction_date', 'desc');

        // --- CATCH EXPORT REQUEST ---
        if ($request->get('export') === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\PurchaseOrderExport($ordersQuery), 
                'Purchase_Orders_' . date('Ymd_His') . '.xlsx'
            );
        }

        $orders = $ordersQuery->paginate(50)
            ->appends(request()->query()); // Menyimpan semua parameter filter di URL

        // FIX N+1: batch-cek status Uang Muka untuk semua PO di halaman ini sekaligus
        $poNumbersOnPage = $orders->pluck('po_number')
            ->map(fn($no) => str_replace('PO-', '', $no))
            ->all();
        $uangMukaSet = DB::table('transaksi_payment_plan')
            ->whereIn('no_transaksi', $poNumbersOnPage)
            ->pluck('no_transaksi')
            ->flip()
            ->all();

        return view('purchase_order.index', compact('orders', 'search', 'uangMukaSet'));
    }

    public function inboundIndex(Request $request)
    {
        $search = $request->get('search');
        
        $orders = PurchaseOrder::with('details')
            ->whereIn('status', ['APPROVED', 'PARTIAL', 'RECEIVED'])
            ->when($search, function($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%");
            })->orderBy('transaction_date', 'desc')->paginate(50)->appends(['search' => $search]);

        return view('purchase_order.inbound', compact('orders', 'search'));
    }

    public function create()
{
    // 1. Ambil data pajak penambah (PPN) yang aktif
    $taxesAddition = Tax::where('is_active', true)->where('tax_type', 'ADDITION')->get();

    // 2. Ambil data pajak pemotong (PPh) yang aktif
    $taxesDeduction = Tax::where('is_active', true)->where('tax_type', 'DEDUCTION')->get();

    // 3. Kirim data tersebut ke View menggunakan compact()
    // (Pastikan nama view 'purchase_order.create' sesuai dengan struktur folder Anda)
    return view('purchase_order.create', compact('taxesAddition', 'taxesDeduction'));
}

    public function store(Request $request)
    {
        $request->validate([
            'po_number'        => 'required|unique:purchase_orders,po_number',
            'transaction_date' => 'required|date',
            'contact_name'     => 'required|string',
            'details'          => 'required|array|min:1',
            'details.*.qty'    => 'required|numeric|min:1',
            'details.*.price'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;

            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $subTotal += ($det['price'] * $det['qty']);
                }
            }

            // --- KALKULASI PAJAK BACKEND ---
            $isIncludePPN = $request->input('is_include_ppn') == '1';
            $taxAdd = Tax::find($request->tax_addition_id);
            $taxDed = Tax::find($request->tax_deduction_id);

            $rateAdd = $taxAdd ? (float) $taxAdd->rate : 0;
            $rateDed = $taxDed ? (float) $taxDed->rate : 0;

            $dpp = $subTotal;
            if ($isIncludePPN && $rateAdd > 0) {
                $dpp = $subTotal / (1 + ($rateAdd / 100));
            }

            $taxAdditionAmount = $dpp * ($rateAdd / 100);
            $taxDeductionAmount = $dpp * ($rateDed / 100);

            if ($isIncludePPN) {
                $grandTotal = $subTotal - $taxDeductionAmount;
            } else {
                $grandTotal = $subTotal + $taxAdditionAmount - $taxDeductionAmount;
            }
            // --------------------------------

            $po = PurchaseOrder::create([
                'po_number'            => $request->po_number,
                'transaction_date'     => $request->transaction_date,
                'contact_name'         => $request->contact_name,
                'location_name'        => 'Pusat',
                'status'               => 'APPROVED',
                'sub_total'            => $subTotal,
                'grand_total'          => $grandTotal, // Simpan Grand Total Valid
                'is_include_ppn'       => $isIncludePPN,
                'tax_addition_id'      => $request->tax_addition_id,
                'tax_deduction_id'     => $request->tax_deduction_id,
                'tax_addition_amount'  => $taxAdditionAmount,
                'tax_deduction_amount' => $taxDeductionAmount,
            ]);

            $detailsToInsert = [];
            $now = now();

            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $detailsToInsert[] = [
                        'purchase_order_id' => $po->id,
                        'product_id'        => $det['product_id'],
                        'qty'               => $det['qty'],
                        'price'             => $det['price'],
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            DB::table('purchase_order_details')->insert($detailsToInsert);

            DB::commit();
            SystemLog::record('CREATE', 'Purchase Order', 'Menambahkan PO: ' . $request->po_number);
            return redirect()->route('purchase-order.index')->with('success', 'Data berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '1024M'); 

        $request->validate(['file_csv' => 'required|file']);
        
        try {
            $content = file_get_contents($request->file('file_csv')->getRealPath());
            
            if (!mb_check_encoding($content, 'UTF-8')) $content = mb_convert_encoding($content, 'UTF-8', 'auto');
            $content = str_replace("\x00", "", $content); 
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $content = preg_replace('/\r\n|\r/', "\n", $content);
            
            $lines = explode("\n", $content);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines, fn($l) => $l !== '');

            if (count($lines) < 2) return redirect()->back()->with('error', 'File CSV kosong.');

            $headerLine = array_shift($lines);
            $delimiter = substr_count($headerLine, ';') >= 8 ? ';' : ',';

            // Kumpulkan semua SKU yang ada di sistem untuk relasi cepat
            $products = Product::pluck('id', 'sku')->toArray();

            DB::beginTransaction();
            $poHeaders = []; // Penampung unik header PO
            $poDetails = [];
            $count = 0;

            foreach ($lines as $line) {
                $r = str_getcsv($line, $delimiter, '"', '\\');

                $tglRaw   = trim($r[0] ?? '');
                $poNumber = trim($r[1] ?? '');
                $itemCode = trim($r[2] ?? '');

                if (empty($poNumber) || strtolower($poNumber) == 'purchase order no.') continue;

                // Parser Tanggal (format lokal '25 Mei 2026' -> 2026-05-25)
                $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des'], ['May', 'Oct', 'Aug', 'Dec'], $tglRaw));
                $tanggal = $time ? date('Y-m-d', $time) : date('Y-m-d');

                // Parser Angka - Menggunakan NumberParser untuk mendukung format campuran (Indonesia/Inggris)
                $price = NumberParser::parseDecimal($r[5] ?? '0');
                $qty = (int) preg_replace('/[^0-9\-]/', '', $r[6] ?? '0');
                $amount = NumberParser::parseDecimal($r[9] ?? '0');
                $subTotal = NumberParser::parseDecimal($r[10] ?? '0');
                $grandTotal = NumberParser::parseDecimal($r[11] ?? '0');
                $discAmount = NumberParser::parseDecimal($r[7] ?? '0');
                $taxAmount = NumberParser::parseDecimal($r[8] ?? '0');

                // Simpan Header Unik ke Memory
                if (!isset($poHeaders[$poNumber])) {
                    $po = PurchaseOrder::updateOrCreate(
                        ['po_number' => $poNumber],
                        [
                            'transaction_date' => $tanggal,
                            'contact_name'     => trim($r[4] ?? ''),
                            'location_name'    => trim($r[12] ?? ''),
                            'sub_total'        => $subTotal,
                            'grand_total'      => $grandTotal,
                            'status'           => 'APPROVED', // Default PO masuk
                        ]
                    );
                    $poHeaders[$poNumber] = $po->id;
                    
                    // Bersihkan detail lama agar jika di-import ulang tidak double
                    PurchaseOrderDetail::where('purchase_order_id', $po->id)->delete();
                }

                $productId = $products[$itemCode] ?? null;

                $poDetails[] = [
                    'purchase_order_id' => $poHeaders[$poNumber],
                    'product_id'        => $productId,
                    'item_code'         => $itemCode,
                    'description'       => trim($r[3] ?? ''),
                    'price'             => $price,
                    'qty_ordered'       => $qty,
                    'qty_received'      => 0, // Default belum datang (Gudang yang update)
                    'disc_amount'       => $discAmount,
                    'tax_amount'        => $taxAmount,
                    'amount'            => $amount,
                    'subtotal'          => $amount, // Sama dengan amount
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
                $count++;
            }

            // Insert Batch Detail
            if (!empty($poDetails)) {
                foreach (array_chunk($poDetails, 1000) as $chunk) {
                    PurchaseOrderDetail::insert($chunk);
                }
            }

            DB::commit();
            SystemLog::record('IMPORT', 'Purchase Order', 'Import PO berhasil. ' . $count . ' baris detail terekam.');
            return redirect()->back()->with('success', "Sinkronisasi Berhasil! {$count} baris detail pesanan (PO) berhasil direkam.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Import PO: ' . $e->getMessage());
        }
    }

    // <-- TAMBAHAN BARU: Fungsi untuk Menerima Barang & Men-generate Jurnal Otomatis
    // ... kode controller lainnya ...

    public function receiveItems(Request $request, $id)
    {
        $request->validate([
            'bill_number'  => 'required|string|max:100',
            'receive_date' => 'required|date',
            'due_date'     => 'nullable|date',
            'items'        => 'required|array',
        ]);

        try {
            $this->poService->receivePartialOrder(
                $id, 
                $request->receive_date,
                $request->items,
                $request->bill_number,
                $request->due_date
            );
            return redirect()->back()->with('success', 'Tagihan (Bill) berhasil dicatat! Jurnal Hutang dan Persediaan telah digenerate otomatis.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses tagihan: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $po = PurchaseOrder::with('details')->findOrFail($id);
        
        // Proteksi: PO yang sudah diterima barangnya tidak boleh diedit
        if ($po->status == 'RECEIVED' || $po->status == 'PARTIAL') {
            return redirect()->route('po.index')->with('error', 'Gagal: PO yang sudah memiliki riwayat penerimaan barang tidak dapat diedit.');
        }

        // Bawa data pajak ke view agar bisa diedit
        $taxesAddition = Tax::where('is_active', true)->where('tax_type', 'ADDITION')->get();
        $taxesDeduction = Tax::where('is_active', true)->where('tax_type', 'DEDUCTION')->get();

        return view('purchase_order.edit', compact('po', 'taxesAddition', 'taxesDeduction'));
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status == 'RECEIVED' || $po->status == 'PARTIAL') {
            return redirect()->route('po.index')->with('error', 'Gagal: PO yang sudah memiliki riwayat penerimaan barang tidak dapat diedit.');
        }

        $request->validate([
            'po_number'        => 'required|unique:purchase_orders,po_number,'.$id,
            'transaction_date' => 'required|date',
            'contact_name'     => 'required|string',
            'details'          => 'required|array|min:1',
            'details.*.qty'    => 'required|numeric|min:1',
            'details.*.price'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;
            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $subTotal += ($det['price'] * $det['qty']);
                }
            }

            // --- KALKULASI PAJAK SAAT UPDATE ---
            $isIncludePPN = $request->input('is_include_ppn') == '1';
            $taxAdd = Tax::find($request->tax_addition_id);
            $taxDed = Tax::find($request->tax_deduction_id);

            $rateAdd = $taxAdd ? (float) $taxAdd->rate : 0;
            $rateDed = $taxDed ? (float) $taxDed->rate : 0;

            $dpp = $subTotal;
            if ($isIncludePPN && $rateAdd > 0) {
                $dpp = $subTotal / (1 + ($rateAdd / 100));
            }

            $taxAdditionAmount = $dpp * ($rateAdd / 100);
            $taxDeductionAmount = $dpp * ($rateDed / 100);

            if ($isIncludePPN) {
                $grandTotal = $subTotal - $taxDeductionAmount;
            } else {
                $grandTotal = $subTotal + $taxAdditionAmount - $taxDeductionAmount;
            }
            // --------------------------------

            $po->update([
                'po_number'            => $request->po_number,
                'transaction_date'     => $request->transaction_date,
                'contact_name'         => $request->contact_name,
                'sub_total'            => $subTotal,
                'grand_total'          => $grandTotal,
                'is_include_ppn'       => $isIncludePPN,
                'tax_addition_id'      => $request->tax_addition_id,
                'tax_deduction_id'     => $request->tax_deduction_id,
                'tax_addition_amount'  => $taxAdditionAmount,
                'tax_deduction_amount' => $taxDeductionAmount,
            ]);

            // Hapus detail lama, ganti dengan yang baru
            PurchaseOrderDetail::where('purchase_order_id', $po->id)->delete();

            $detailsToInsert = [];
            $now = now();
            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $detailsToInsert[] = [
                        'purchase_order_id' => $po->id,
                        'product_id'        => null,
                        'item_code'         => $det['item_code'],
                        'description'       => $det['description'] ?? '-',
                        'price'             => $det['price'],
                        'qty'               => $det['qty'],
                        'qty_received'      => 0,
                        'amount'            => $det['price'] * $det['qty'],
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            if(count($detailsToInsert) > 0) {
                PurchaseOrderDetail::insert($detailsToInsert);
            }

            DB::commit();
            SystemLog::record('UPDATE', 'Purchase Order', 'Mengubah data PO: ' . $po->po_number);
            return redirect()->route('po.index')->with('success', 'Purchase Order berhasil diperbarui dengan rincian pajak baru!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update PO: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        
        if ($po->status == 'RECEIVED' || $po->status == 'PARTIAL') {
            return redirect()->route('po.index')->with('error', 'Gagal: PO yang sudah ada riwayat penerimaan barang tidak dapat dihapus.');
        }

        DB::beginTransaction();
        try {
            PurchaseOrderDetail::where('purchase_order_id', $po->id)->delete();
            $po->delete();
            SystemLog::record('DELETE', 'Purchase Order', 'Membatalkan dan menghapus PO: ' . $po->po_number);
            DB::commit();
            return redirect()->route('po.index')->with('success', 'Purchase Order berhasil dibatalkan dan dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus PO: ' . $e->getMessage());
        }
    }

    public function voidReceipt($id)
    {
        try {
            $po = PurchaseOrder::findOrFail($id);
            $this->poService->voidReceipt($id);
            SystemLog::record('VOID', 'Purchase Order', 'Membatalkan penerimaan barang PO: ' . $po->po_number);
            return redirect()->back()->with('success', 'Penerimaan Barang berhasil dibatalkan! Jurnal aset dihapus dan Stok Gudang dikembalikan ke posisi semula.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan penerimaan: ' . $e->getMessage());
        }
    }

    // ... kode controller lainnya ...

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Import_PO.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM agar terbaca rapi saat dibuka di Microsoft Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Baris 1: Header Kolom (Wajib sama persis dengan urutan array parser)
            fputcsv($file, [
                'Tanggal', 'Purchase Order No.', 'Item Code', 'Description', 
                'Contact', 'Harga Satuan', 'Qty', 'Diskon', 'Pajak', 
                'Total', 'Subtotal', 'Grand Total', 'Lokasi'
            ], ';');
            
            // Baris 2: Data Contoh
            fputcsv($file, [
                '10 Jun 2026', 'PO-0001', 'SKU-001', 'Barang Contoh PO', 
                'PT Supplier Maju', '50000', '10', '0', '50000', 
                '500000', '500000', '550000', 'Pusat'
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

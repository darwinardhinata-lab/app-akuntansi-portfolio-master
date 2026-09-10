<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Product;
use App\Models\SystemLog;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    protected $soService;

    public function __construct(SalesOrderService $soService)
    {
        $this->soService = $soService;
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $tab = $request->get('tab', 'semua'); // Ambil parameter tab dari URL

        $ordersQuery = SalesOrder::with(['details', 'salesInvoice'])
            // FIX WMS: Filter query berdasarkan Tab yang aktif. Sebelumnya keliru mencocokkan ke kolom
            // 'status' (status akuntansi, isinya cuma APPROVED/SHIPPED) sehingga semua tab WMS selalu kosong.
            // Sekarang dipisah: 'belum_dibayar' pakai flag is_paid, sisanya pakai kolom wms_status dari Jubelio.
            ->when($tab !== 'semua', function($q) use ($tab) {
                if ($tab === 'belum_dibayar') {
                    $q->where('is_paid', 0);
                } else {
                    $statusMap = [
                        'gagal_download' => 'DOWNLOAD_FAILED',
                        'siap_proses'    => 'READY_TO_PROCESS',
                        'stok_kosong'    => 'OUT_OF_STOCK',
                        'gagal_picking'  => 'PICKING_FAILED',
                        'request_batal'  => 'CANCEL_REQUESTED',
                        'batal'          => 'CANCELED',
                        'diretur'        => 'RETURNED'
                    ];
                    if (array_key_exists($tab, $statusMap)) {
                        $q->where('wms_status', $statusMap[$tab]);
                    }
                }
            })
            ->when($search, function($q) use ($search) {
                $q->where(function($query) use ($search) {
                    $query->where('so_number', 'like', "%{$search}%")
                          ->orWhere('contact_name', 'like', "%{$search}%");
                });
            })
            ->when($start_date && $end_date, function($q) use ($start_date, $end_date) {
                $q->whereBetween('transaction_date', [$start_date, $end_date]);
            })
            ->orderBy('transaction_date', 'desc');

        // --- CATCH EXPORT REQUEST ---
        if ($request->get('export') === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SalesOrderExport($ordersQuery), 
                'Sales_Orders_' . date('Ymd_His') . '.xlsx'
            );
        }

    $orders = $ordersQuery->paginate(50)
        ->appends(request()->query());

    // WAJIB: Tarik Master Barang untuk dropdown Barang Substitusi di Modal Pengiriman
    $products = Product::orderBy('name', 'asc')->get();

    // --- ANALITIK PIVOT DATA ---
    $analyticData = [];
    if (isset($tab) && $tab == 'analisa') {
        $analyticData = SalesOrder::with('details')
            ->where('status', 'SHIPPED')
            ->whereNotNull('invoice_no')
            ->get()
            ->map(function($so) {
                return [
                    'lokasi' => $so->location_name ?? 'Pusat',
                    'bulan' => date('Y-m', strtotime($so->transaction_date)),
                    'sku' => $so->details->pluck('item_code')->join(', '),
                    'pelanggan' => $so->contact_name,
                    'total_omset' => (float) $so->grand_total,
                    'total_qty' => (int) $so->details->sum('qty')
                ];
            });
    }

    return view('sales_order.index', compact('orders', 'search', 'products', 'tab', 'analyticData'));
    }

    public function import(Request $request)
    {
        ini_set('max_execution_time', 0); // UNLIMITED TIME
        ini_set('memory_limit', '1024M'); 
        DB::disableQueryLog(); // Matikan log agar RAM tidak bocor

        $request->validate(['file_csv' => 'required|file']);
        
        try {
            $filePath = $request->file('file_csv')->getRealPath();
            $handle = fopen($filePath, 'r');
            
            if (!$handle) {
                return redirect()->back()->with('error', 'Gagal membaca file CSV. Pastikan file tidak *corrupt*.');
            }

            // ======================================================================
            // 1. STREAM HEADER HUNTER (Scan max 50 baris pertama)
            // ======================================================================
            $bestScore = -1;
            $headerRowIndex = -1;
            $headerCols = [];
            $delimiter = ',';
            $currentLine = 0;

            while (($lineStr = fgets($handle)) !== false && $currentLine < 50) {
                $cleanLine = str_replace([';', ',', '"', "'", "\t", " ", "\n", "\r", "\x00"], '', $lineStr);
                $cleanLine = preg_replace('/^\xEF\xBB\xBF/', '', $cleanLine); // Bersihkan BOM
                
                if (trim($cleanLine) === '') {
                    $currentLine++;
                    continue;
                }

                $countSemi = substr_count($lineStr, ';');
                $countComma = substr_count($lineStr, ',');
                $tempDelimiter = $countSemi > $countComma ? ';' : ',';

                $cols = str_getcsv(strtolower($lineStr), $tempDelimiter, '"', '\\');
                $cols = array_map('trim', $cols);
                
                $score = 0;
                $rowStr = implode(' ', $cols);
                
                if (stripos($rowStr, 'pesanan') !== false || stripos($rowStr, 'salesorder_no') !== false) $score++;
                if (stripos($rowStr, 'tanggal') !== false || stripos($rowStr, 'date') !== false) $score++;
                if (stripos($rowStr, 'pelanggan') !== false || stripos($rowStr, 'customer') !== false) $score++;
                if (stripos($rowStr, 'qty') !== false || stripos($rowStr, 'jumlah') !== false) $score++;
                if (stripos($rowStr, 'harga') !== false || stripos($rowStr, 'price') !== false) $score++;
                if (stripos($rowStr, 'sku') !== false) $score++;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $headerRowIndex = $currentLine;
                    $headerCols = $cols;
                    $delimiter = $tempDelimiter;
                }

                if ($score >= 4) break; 
                $currentLine++;
            }

            if ($headerRowIndex === -1 || $bestScore < 2) {
                fclose($handle);
                return redirect()->back()->with('error', 'Gagal: Tidak dapat menemukan baris Header (Judul Kolom) di file CSV Anda.');
            }

            // ======================================================================
            // 2. PEMETAAN INDEX KOLOM
            // ======================================================================
            $findCol = function($keywords) use ($headerCols) {
                foreach ($headerCols as $idx => $colName) {
                    foreach ($keywords as $key) {
                        if (trim($colName) == $key || stripos($colName, $key) !== false) return $idx;
                    }
                }
                return -1; 
            };

            $idxTgl      = $findCol(['tanggal', 'transaction_date']);
            $idxSo       = $findCol(['no pesanan', 'no. pesanan', 'pesanan', 'salesorder_no']);
            $idxCustomer = $findCol(['pelanggan', 'customer_name', 'penerima']);
            $idxSku      = $findCol(['sku', 'item_code', 'kode barang']);
            $idxDesc     = $findCol(['nama barang', 'description', 'deskripsi']);
            $idxPrice    = $findCol(['harga jual', 'harga', 'price']);
            $idxQty      = $findCol(['qty', 'kuantitas', 'jumlah']);
            $idxDisc     = $findCol(['diskon', 'disc']);
            $idxTax      = $findCol(['pajak', 'tax']);
            $idxAmount   = $findCol(['amount', 'total', 'jumlah harga']);
            $idxStatus   = $findCol(['status', 'internal_status']);
            $idxLokasi   = $findCol(['nama toko', 'lokasi', 'location_name', 'gudang']);

            if ($idxSo === -1 || $idxQty === -1 || $idxPrice === -1) {
                fclose($handle);
                return redirect()->back()->with('error', "Gagal: Kolom wajib (No Pesanan, QTY, Harga Jual) tidak ditemukan.");
            }

            $products = Product::pluck('id', 'sku')->toArray();

            // REWIND Kursor File kembali ke paling atas
            rewind($handle);
            // Lompati baris sampah sampai ke bawah Header
            for ($i = 0; $i <= $headerRowIndex; $i++) { fgets($handle); }

            DB::beginTransaction();
            
            $soHeaders = []; 
            $soDetails = [];
            $autoShipList = []; 
            $count = 0;
            $batchSize = 2000; // Simpan ke database per 2000 baris agar ringan

            // ======================================================================
            // 3. BACA DATA BARIS DEMI BARIS (STREAMING)
            // ======================================================================
            while (($r = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== FALSE) {
                if (!isset($r[$idxSo])) continue;

                $soNumber = trim($r[$idxSo]);
                if (empty($soNumber) || strtolower($soNumber) == 'no pesanan' || strtolower($soNumber) == 'sales order no.') {
                    continue;
                }

                $qtyRaw = $r[$idxQty] ?? '0';
                if (preg_replace('/[^0-9]/', '', $qtyRaw) === '' || preg_replace('/[^0-9]/', '', $qtyRaw) == '0') {
                    continue; 
                }

                $tglRaw   = $idxTgl >= 0 ? trim($r[$idxTgl]) : '';
                $time = strtotime(str_replace(['Mei', 'Okt', 'Ags', 'Des', '/'], ['May', 'Oct', 'Aug', 'Dec', '-'], $tglRaw));
                $tanggal = $time ? date('Y-m-d', $time) : date('Y-m-d');
                
                $rawStatus = $idxStatus >= 0 ? strtolower(trim($r[$idxStatus])) : 'selesai';
                $isCompleted = in_array($rawStatus, ['selesai', 'completed', 'shipped', 'lunas']);

                // FIX: Menggunakan Helper Class agar DRY (Don't Repeat Yourself)
                $price      = \App\Support\NumberParser::clean($r[$idxPrice] ?? '0');
                $qty        = (int)\App\Support\NumberParser::clean($r[$idxQty] ?? '0');
                $disc       = $idxDisc >= 0 ? \App\Support\NumberParser::clean($r[$idxDisc] ?? '0') : 0;
                $tax        = $idxTax >= 0 ? \App\Support\NumberParser::clean($r[$idxTax] ?? '0') : 0;
                $amount     = $idxAmount >= 0 ? \App\Support\NumberParser::clean($r[$idxAmount] ?? '0') : ($price * $qty);
                
                $itemCode   = $idxSku >= 0 ? trim($r[$idxSku] ?? '') : 'PRODUK-UMUM';
                $desc       = $idxDesc >= 0 ? trim($r[$idxDesc] ?? '') : '-';
                $customer   = $idxCustomer >= 0 ? trim($r[$idxCustomer] ?? '') : 'Pelanggan Umum';
                $lokasi     = $idxLokasi >= 0 ? trim($r[$idxLokasi] ?? '') : 'Pusat';
                
                if (empty($customer)) $customer = 'Pelanggan Umum';

                if (!isset($soHeaders[$soNumber])) {
                    $so = SalesOrder::updateOrCreate(
                        ['so_number' => $soNumber],
                        [
                            'transaction_date' => $tanggal,
                            'contact_name'     => $customer,
                            'location_name'    => $lokasi,
                            'sub_total'        => 0, 
                            'disc_amount'      => 0,
                            'tax_amount'       => 0,
                            'grand_total'      => 0, 
                            'status'           => 'APPROVED', 
                        ]
                    );
                    $soHeaders[$soNumber] = $so->id;
                    
                    if ($isCompleted) {
                        $autoShipList[$soNumber] = $so->id;
                    }
                    SalesOrderDetail::where('sales_order_id', $so->id)->delete();
                }

                $productId = $products[$itemCode] ?? null;

                $soDetails[] = [
                    'sales_order_id' => $soHeaders[$soNumber],
                    'product_id'     => $productId,
                    'item_code'      => $itemCode,
                    'description'    => $desc,
                    'price'          => $price,
                    'qty'            => $qty,
                    'qty_shipped'    => 0,
                    'disc_amount'    => $disc,
                    'tax_amount'     => $tax,
                    'amount'         => $amount,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
                $count++;

                // BATCH INSERT: Jika sudah 2000 data, buang ke database & kosongkan RAM
                if (count($soDetails) >= $batchSize) {
                    SalesOrderDetail::insert($soDetails);
                    $soDetails = [];
                }
            }

            fclose($handle); // Tutup file untuk rilis RAM

            // Insert sisa data yang kurang dari 2000
            if (!empty($soDetails)) {
                SalesOrderDetail::insert($soDetails);
            }

            // FIX: Batch Update Total Kalkulasi SO menggunakan CASE WHEN agar hemat query
            $soIds = array_values($soHeaders);
            if (!empty($soIds)) {
                $totals = SalesOrderDetail::whereIn('sales_order_id', $soIds)
                    ->selectRaw('sales_order_id, SUM(amount) as total')
                    ->groupBy('sales_order_id')
                    ->pluck('total', 'sales_order_id');

                foreach (array_chunk($soIds, 500) as $chunkIds) {
                    $cases = '';
                    foreach ($chunkIds as $soId) {
                        $total = $totals[$soId] ?? 0;
                        $cases .= "WHEN {$soId} THEN {$total} ";
                    }
                    $inClause = implode(',', $chunkIds);
                    
                    DB::statement("
                        UPDATE sales_orders 
                        SET sub_total = CASE id {$cases} END, 
                            grand_total = CASE id {$cases} END 
                        WHERE id IN ({$inClause})
                    ");
                }
            }

            DB::commit();

            // =========================================================================
            // 4. MESIN FAKTUR & JURNAL OTOMATIS
            // =========================================================================
            // FIX SECURITY: Jika import via UI Web, WAJIB mem-posting jurnal.
            // Opsi skip_journal murni hanya bisa diakses via Terminal (Command Line)
            $skipJournal = false;
            $autoJournalCount = 0;
            
            if (count($autoShipList) > 0) {
                foreach ($autoShipList as $soNumber => $soId) {
                    $so = SalesOrder::with('details')->find($soId);
                    
                    if ($so && $so->status === 'APPROVED') {
                        $itemsToShip = [];
                        foreach ($so->details as $det) {
                            $itemsToShip[] = ['item_code' => $det->item_code, 'qty' => $det->qty, 'price' => $det->price, 'is_substitution' => false];
                        }

                        $financials = [
                            'sub_total'         => $so->sub_total,
                            'disc_amount'       => $so->disc_amount + $so->other_discount,
                            'tax_amount'        => $so->tax_amount,
                            'shipping_cost'     => $so->shipping_cost,
                            'shipping_discount' => $so->shipping_discount,
                            'other_cost'        => $so->other_cost,
                            'return_remaining'  => $so->return_remaining,
                            'grand_total'       => $so->grand_total,
                        ];
                        
                        try {
                            $this->soService->createInvoiceAndShip($so->id, $so->transaction_date, $itemsToShip, $financials, $skipJournal);
                            $autoJournalCount++;
                        } catch (\Exception $e) { continue; }
                    }
                }
            }

            SystemLog::record('IMPORT', 'Sales Order', 'Import SO berhasil. ' . $count . ' baris item terekam.');
            $msg = "File Rincian Penjualan (Metode Streaming) Berhasil Diproses! {$count} baris item pesanan terekam.";
            if ($autoJournalCount > 0) {
                $msg .= " Sistem mencetak {$autoJournalCount} Faktur & " . ($skipJournal ? "Mengabaikan Jurnal Akuntansi." : "Memposting Jurnal.");
            }

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Import SO: ' . explode(' (Connection', $e->getMessage())[0]);
        }
    }

    public function processShipment(Request $request, $id)
    {
        ini_set('max_execution_time', 0); 
        ini_set('memory_limit', '1024M'); 
        DB::disableQueryLog();

        // 👇 TAMBAHKAN 3 BARIS INI UNTUK MEMBUNUH DEBUGBAR SEMENTARA 👇
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
        // 👆 ----------------------------------------------------- 👆

        $request->validate(['file_csv' => 'required|file']);
        $request->validate([
            'ship_date'   => 'required|date',
            'items'       => 'required|array',
            'sub_total'   => 'required|numeric',
            'grand_total' => 'required|numeric',
        ]);

        try {
            // Bersihkan array: Buang barang yang kuantitas pengirimannya 0
            $actualItems = array_filter($request->items, function($item) {
                return isset($item['qty']) && (int)$item['qty'] > 0;
            });

            if (empty($actualItems)) {
                return redirect()->back()->with('error', 'Gagal: Kuantitas barang aktual yang dikirim tidak boleh kosong semua.');
            }

            // Tangkap kalkulasi dari layar
            $financials = [
                'sub_total'         => $request->sub_total,
                'disc_amount'       => $request->disc_amount ?? 0,
                'tax_amount'        => $request->tax_amount ?? 0,
                'shipping_cost'     => $request->shipping_cost ?? 0,
                'shipping_discount' => $request->shipping_discount ?? 0,
                'other_cost'        => $request->other_cost ?? 0,
                'return_remaining'  => $request->return_remaining ?? 0,
                'grand_total'       => $request->grand_total,
            ];

            // Tembak ke Service Penjurnal yang baru
            $this->soService->createInvoiceAndShip($id, $request->ship_date, $actualItems, $financials);

            SystemLog::record('SHIP', 'Sales Order', 'Proses pengiriman dan faktur SO ID: ' . $id);

            return redirect()->back()->with('success', 'SUKSES! Faktur Penjualan Aktual (Invoice) berhasil diterbitkan. Stok Gudang dan Jurnal Keuangan telah diposting berdasarkan data faktur.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses faktur pengiriman: ' . $e->getMessage());
        }
    }

    public function rollbackShipment($id)
    {
        try {
            $so = SalesOrder::findOrFail($id);
            $this->soService->voidShipment($id);
            SystemLog::record('VOID', 'Sales Order', 'Membatalkan pengiriman SO: ' . $so->so_number);
            return redirect()->back()->with('success', 'Pembatalan pengiriman berhasil dilakukan! Stok gudang dikembalikan dan jurnal akuntansi telah dihapus murni.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan pengiriman: ' . $e->getMessage());
        }
    }

    public function dispatchSyncJob()
    {
        try {
            \App\Jobs\SyncSODashboardToTempJob::dispatch();
            return response()->json(['status' => 'success', 'message' => 'Sync job dispatched']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ... [method index dan import biarkan seperti semula] ...

    public function create()
    {
        // Tarik data master untuk dropdown
        $products = Product::orderBy('name', 'asc')->get();
        $taxes = \App\Models\Tax::where('is_active', true)->get();
        
        // Generate nomor SO sementara untuk display (bisa diedit user)
        // P2-2: Gunakan DocumentSequence (row-lock) untuk menghindari race condition
        $autoNumber = \App\Support\DocumentSequence::preview(
            'sales_orders', 'so_number', 'SO-' . date('ymd') . '-'
        );

        return view('sales_order.create', compact('products', 'taxes', 'autoNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'so_number'        => 'required|unique:sales_orders,so_number',
            'transaction_date' => 'required|date',
            'contact_name'     => 'required|string',
            'receiver_name'    => 'required|string',
            'details'          => 'required|array|min:1',
            'details.*.qty'    => 'required|numeric|min:1',
            'details.*.price'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;
            $totalDiscItems = 0;

            // Hitung subtotal dan diskon per baris item
            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $barisTotal = ($det['price'] * $det['qty']);
                    $barisDisc = $det['disc_amount'] ?? 0;
                    
                    $subTotal += $barisTotal;
                    $totalDiscItems += $barisDisc;
                }
            }

            // Tangkap kalkulasi biaya lainnya dari request
            $otherDisc = $request->other_discount ?? 0;
            $taxAmount = $request->tax_amount ?? 0;
            $shippingCost = $request->shipping_cost ?? 0;
            $shippingDisc = $request->shipping_discount ?? 0;
            $otherCost = $request->other_cost ?? 0;
            $returnRem = $request->return_remaining ?? 0;

            // Kalkulasi Grand Total mutlak backend (Validasi Keamanan)
            $grandTotal = ($subTotal - $totalDiscItems - $otherDisc) 
                          + $taxAmount 
                          + ($shippingCost - $shippingDisc) 
                          + $otherCost 
                          - $returnRem;

            // 1. Simpan Header SO
            $so = SalesOrder::create([
                'so_number'           => $request->so_number,
                'transaction_date'    => $request->transaction_date,
                'contact_name'        => $request->contact_name,
                'ref_number'          => $request->ref_number,
                'salesman'            => $request->salesman,
                'source'              => $request->source,
                'store_name'          => $request->store_name,
                'location_name'       => $request->location_name,
                'remarks'             => $request->remarks,
                'is_tax_included'     => $request->is_tax_included ? 1 : 0,
                
                'receiver_name'       => $request->receiver_name,
                'receiver_address'    => $request->receiver_address,
                'receiver_phone'      => $request->receiver_phone,
                
                'is_cod'              => $request->is_cod ? 1 : 0,
                'tracking_number'     => $request->tracking_number,
                'total_weight'        => $request->total_weight ?? 0,
                'is_marketplace_shipment' => $request->is_marketplace_shipment ? 1 : 0,
                'courier'             => $request->courier,
                
                'status'              => 'APPROVED',
                'is_paid'             => $request->is_paid ? 1 : 0,
                
                'sub_total'           => $subTotal,
                'disc_amount'         => $totalDiscItems,
                'other_discount'      => $otherDisc,
                'tax_amount'          => $taxAmount,
                'shipping_cost'       => $shippingCost,
                'shipping_discount'   => $shippingDisc,
                'other_cost'          => $otherCost,
                'return_remaining'    => $returnRem,
                'grand_total'         => $grandTotal,
            ]);

            // 2. Simpan Detail Item
            $detailsToInsert = [];
            $now = now();
            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $barisSub = ($det['price'] * $det['qty']);
                    $barisBersih = $barisSub - ($det['disc_amount'] ?? 0);

                    $detailsToInsert[] = [
                        'sales_order_id' => $so->id,
                        'product_id'     => $det['product_id'] ?? null,
                        'item_code'      => $det['item_code'],
                        'description'    => $det['description'] ?? '-',
                        'price'          => $det['price'],
                        'qty'            => $det['qty'],
                        'qty_shipped'    => 0, // Belum dikirim
                        'disc_amount'    => $det['disc_amount'] ?? 0,
                        'amount'         => $barisBersih,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            if(count($detailsToInsert) > 0) {
                SalesOrderDetail::insert($detailsToInsert);
            }

            DB::commit();
            SystemLog::record('CREATE', 'Sales Order', 'Menambahkan SO: ' . $request->so_number);
            return redirect()->route('so.index')->with('success', 'Pesanan Penjualan (SO) berhasil dibuat secara manual.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan pesanan: ' . $e->getMessage());
        }
    }

    // ... kode controller lainnya ...

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Import_SO_Jubelio.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Baris 1: Header Kolom
            fputcsv($file, [
                'Tanggal', 'Sales Order No.', 'Item Code', 'Description', 
                'Customer', 'Harga Satuan', 'Qty', 'Diskon', 'Pajak', 
                'Total', 'Subtotal', 'Grand Total', 'Lokasi'
            ], ';');
            
            // Baris 2: Data Contoh
            fputcsv($file, [
                '10 Jun 2026', 'SO-0001', 'SKU-001', 'Barang Contoh SO', 
                'Budi Pelanggan', '75000', '2', '0', '15000', 
                '150000', '150000', '165000', 'Pusat'
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function edit($id)
    {
        $so = SalesOrder::with('details')->findOrFail($id);
        
        if ($so->status == 'SHIPPED') {
            return redirect()->route('so.index')->with('error', 'Gagal: Sales Order yang sudah dikirim tidak dapat diedit kembali.');
        }

        $products = Product::orderBy('name', 'asc')->get();

        return view('sales_order.edit', compact('so', 'products'));
    }

    public function update(Request $request, $id)
    {
        $so = SalesOrder::findOrFail($id);

        if ($so->status == 'SHIPPED') {
            return redirect()->route('so.index')->with('error', 'Gagal: Sales Order yang sudah dikirim tidak dapat diedit kembali.');
        }

        $request->validate([
            'so_number'        => 'required|unique:sales_orders,so_number,'.$id,
            'transaction_date' => 'required|date',
            'contact_name'     => 'required|string',
            'receiver_name'    => 'required|string',
            'details'          => 'required|array|min:1',
            'details.*.qty'    => 'required|numeric|min:1',
            'details.*.price'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;
            $totalDiscItems = 0;

            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $barisTotal = ($det['price'] * $det['qty']);
                    $barisDisc = $det['disc_amount'] ?? 0;
                    
                    $subTotal += $barisTotal;
                    $totalDiscItems += $barisDisc;
                }
            }

            $otherDisc = $request->other_discount ?? 0;
            $taxAmount = $request->tax_amount ?? 0;
            $shippingCost = $request->shipping_cost ?? 0;
            $shippingDisc = $request->shipping_discount ?? 0;
            $otherCost = $request->other_cost ?? 0;
            $returnRem = $request->return_remaining ?? 0;

            $grandTotal = ($subTotal - $totalDiscItems - $otherDisc) 
                          + $taxAmount 
                          + ($shippingCost - $shippingDisc) 
                          + $otherCost 
                          - $returnRem;

            $so->update([
                'so_number'           => $request->so_number,
                'transaction_date'    => $request->transaction_date,
                'contact_name'        => $request->contact_name,
                'ref_number'          => $request->ref_number,
                'salesman'            => $request->salesman,
                'source'              => $request->source,
                'store_name'          => $request->store_name,
                'location_name'       => $request->location_name,
                'remarks'             => $request->remarks,
                'is_tax_included'     => $request->is_tax_included ? 1 : 0,
                
                'receiver_name'       => $request->receiver_name,
                'receiver_address'    => $request->receiver_address,
                'receiver_phone'      => $request->receiver_phone,
                
                'is_cod'              => $request->is_cod ? 1 : 0,
                'tracking_number'     => $request->tracking_number,
                'total_weight'        => $request->total_weight ?? 0,
                'is_marketplace_shipment' => $request->is_marketplace_shipment ? 1 : 0,
                'courier'             => $request->courier,
                
                'is_paid'             => $request->is_paid ? 1 : 0,
                
                'sub_total'           => $subTotal,
                'disc_amount'         => $totalDiscItems,
                'other_discount'      => $otherDisc,
                'tax_amount'          => $taxAmount,
                'shipping_cost'       => $shippingCost,
                'shipping_discount'   => $shippingDisc,
                'other_cost'          => $otherCost,
                'return_remaining'    => $returnRem,
                'grand_total'         => $grandTotal,
            ]);

            // Hapus rincian barang lama
            SalesOrderDetail::where('sales_order_id', $so->id)->delete();

            // Masukkan rincian barang baru
            $detailsToInsert = [];
            $now = now();
            foreach ($request->details as $det) {
                if(isset($det['qty']) && $det['qty'] > 0) {
                    $barisSub = ($det['price'] * $det['qty']);
                    $barisBersih = $barisSub - ($det['disc_amount'] ?? 0);

                    $detailsToInsert[] = [
                        'sales_order_id' => $so->id,
                        'product_id'     => $det['product_id'] ?? null,
                        'item_code'      => $det['item_code'],
                        'description'    => $det['description'] ?? '-',
                        'price'          => $det['price'],
                        'qty'            => $det['qty'],
                        'qty_shipped'    => 0,
                        'disc_amount'    => $det['disc_amount'] ?? 0,
                        'amount'         => $barisBersih,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            if(count($detailsToInsert) > 0) {
                SalesOrderDetail::insert($detailsToInsert);
            }

            DB::commit();
            SystemLog::record('UPDATE', 'Sales Order', 'Mengubah data SO: ' . $so->so_number);
            return redirect()->route('so.index')->with('success', 'Pesanan Penjualan (SO) berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengupdate pesanan: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $so = SalesOrder::findOrFail($id);

        if ($so->status == 'SHIPPED') {
            return redirect()->route('so.index')->with('error', 'Gagal: Sales Order yang sudah dikirim tidak dapat dihapus.');
        }

        DB::beginTransaction();
        try {
            SalesOrderDetail::where('sales_order_id', $so->id)->delete();
            $so->delete();
            SystemLog::record('DELETE', 'Sales Order', 'Membatalkan dan menghapus SO: ' . $so->so_number);
            DB::commit();
            return redirect()->route('so.index')->with('success', 'Pesanan Penjualan (SO) berhasil dibatalkan dan dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus SO: ' . $e->getMessage());
        }
    }

}
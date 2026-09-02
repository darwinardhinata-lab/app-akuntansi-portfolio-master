<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Account;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function inbound(Request $request) {
        $tab = $request->get('tab', 'penerimaan_barang');
        $query = InventoryLedger::with('product')->where('type', 'IN');

        // Filter Logic Berdasarkan Flow Data
        if ($tab == 'pesanan_pembelian') {
            $query->where('description', 'LIKE', '%Penerimaan PO%');
        } elseif ($tab == 'transfer_masuk') {
            $query->where('evidence_number', 'LIKE', 'IN-%'); // Transaksi masuk manual
        } elseif ($tab == 'retur_online') {
            $query->where(function($q) {
                $q->where('evidence_number', 'LIKE', 'RET-%')->orWhere('evidence_number', 'LIKE', 'SR-%');
            });
        } elseif ($tab == 'penerimaan_barang') {
            $query->where('evidence_number', 'LIKE', 'BIL-%'); // Terkoneksi dengan Bill
        } elseif ($tab == 'penempatan_barang') {
            $query->where('evidence_number', 'LIKE', 'PUT-%'); // Placeholder WMS Putaway
        }

        $ledgers = $query->orderBy('transaction_date', 'desc')->paginate(50)->appends(['tab' => $tab]);
        return view('warehouse.inbound', compact('ledgers', 'tab'));
    }

    public function outbound(Request $request) {
        $tab = $request->get('tab', 'transfer_keluar');
        $query = InventoryLedger::with('product')->where('type', 'OUT');

        // Filter Logic Berdasarkan Flow Data
        if ($tab == 'retur_pembelian') {
            $query->where(function($q) {
                $q->where('evidence_number', 'LIKE', 'PR-%')->orWhere('description', 'LIKE', '%Retur Pembelian%');
            });
        } elseif ($tab == 'transfer_keluar') {
            $query->where('evidence_number', 'LIKE', 'OUT-%'); // Transaksi keluar manual
        }

        $ledgers = $query->orderBy('transaction_date', 'desc')->paginate(50)->appends(['tab' => $tab]);
        return view('warehouse.outbound', compact('ledgers', 'tab'));
    }

    public function processOrders(Request $request) {
        $tab = $request->get('tab', 'picking');
        $query = \App\Models\SalesOrder::query();

        // Filter Logic WMS Jubelio / Pipeline Internal
        if ($tab == 'picking') {
            $query->whereIn('wms_status', ['PICK', 'FINISH_PICK', 'PRINT_PICK'])
                  ->orWhere(function($q) {
                      $q->where('status', 'APPROVED')->whereNull('wms_status');
                  });
        } elseif ($tab == 'packing') {
            $query->whereIn('wms_status', ['PACK', 'FINISH_PACK']);
        } elseif ($tab == 'shipping') {
            $query->whereIn('wms_status', ['READY_TO_SHIP', 'SHIPPING']);
        } elseif ($tab == 'sudah_dikirim') {
            $query->where('status', 'SHIPPED');
        } elseif ($tab == 'selesai') {
            $query->where('status', 'COMPLETED');
        }

        $orders = $query->orderBy('transaction_date', 'asc')->paginate(50)->appends(['tab' => $tab]);
        return view('warehouse.process_orders', compact('orders', 'tab'));
    }

    public function createInbound(Request $request) {
        $tab = $request->get('tab', 'pembelian');
        $products = Product::orderBy('name', 'asc')->get();
        $accounts = Account::orderBy('account_code', 'asc')->get();
        $autoNumberInbound = 'IN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $suppliers = [];
        $invoices = [];
        $autoNumberRetur = '';

        if ($tab == 'pembelian') {
            $suppliers = \App\Models\PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIAL'])
                ->select('contact_name')->distinct()->pluck('contact_name');
        } elseif ($tab == 'retur_penjualan') {
            $invoices = \App\Models\SalesInvoice::with('salesOrder')
                ->orderBy('transaction_date', 'desc')->limit(300)->get();
                
            $lastReturn = \App\Models\SalesReturn::orderBy('id', 'desc')->first();
            $lastNumber = $lastReturn ? (int) substr($lastReturn->return_number, -4) : 0;
            $autoNumberRetur = 'SR-' . date('Ymd') . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }

        return view('warehouse.create_inbound', compact('products', 'accounts', 'autoNumberInbound', 'tab', 'suppliers', 'invoices', 'autoNumberRetur'));
    }

    // Method AJAX baru untuk mengambil PO via Dropdown Supplier
    public function getPoBySupplier(Request $request) {
        $supplier = $request->get('supplier');
        $pos = \App\Models\PurchaseOrder::where('contact_name', $supplier)
            ->whereIn('status', ['APPROVED', 'PARTIAL'])
            ->get(['id', 'po_number', 'grand_total', 'transaction_date']);
        return response()->json($pos);
    }

    public function getPoDetailsAjax($id) {
        $po = \App\Models\PurchaseOrder::with('details')->findOrFail($id);
        return response()->json(['po' => $po, 'items' => $po->details]);
    }

    public function storeInbound(Request $request) {
        $request->validate(['evidence_number' => 'required', 'transaction_date' => 'required|date', 'offset_account' => 'required', 'items' => 'required|array']);
        DB::beginTransaction();
        try {
            $now = now();
            $totalValue = 0;
            $ledgers = [];

            foreach ($request->items as $item) {
                if (isset($item['qty']) && $item['qty'] > 0) {
                    // FIX: Kunci baris produk dengan lockForUpdate
                    $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();
                    $qty = (int) $item['qty'];
                    $cost = (float) $item['unit_cost'];
                    
                    $oldStock = $product->stock_quantity;
                    $oldMac = $product->average_cost;
                    $newStock = $oldStock + $qty;
                    $newValue = ($oldStock * $oldMac) + ($qty * $cost);
                    $newMac = $newStock > 0 ? ($newValue / $newStock) : 0;
                    
                    $product->update(['stock_quantity' => $newStock, 'average_cost' => $newMac]);
                    $lineTotal = $qty * $cost;
                    $totalValue += $lineTotal;

                    $ledgers[] = [
                        'transaction_date' => $request->transaction_date, 'evidence_number' => $request->evidence_number,
                        'product_id' => $product->id, 'type' => 'IN', 'qty' => $qty, 'unit_cost' => $cost, 'total_cost' => $lineTotal,
                        'running_qty' => $newStock, 'running_value' => $newStock * $newMac, 'moving_average_cost' => $newMac,
                        'description' => $request->description ?? 'Barang Masuk Manual', 'created_at' => $now, 'updated_at' => $now
                    ];
                }
            }
            if ($totalValue > 0) {
                InventoryLedger::insert($ledgers);
                $jh = JournalHeader::create(['transaction_date' => $request->transaction_date, 'evidence_number' => $request->evidence_number, 'description' => $request->description ?? 'Barang Masuk Manual', 'source_doc_no' => $request->evidence_number, 'transaction_type' => 'Inbound']);
                JournalDetail::insert([
                    ['journal_id' => $jh->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'DEBET', 'amount' => $totalValue, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                    ['journal_id' => $jh->getKey(), 'account_code' => $request->offset_account, 'position' => 'KREDIT', 'amount' => $totalValue, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null]
                ]);
            }
            DB::commit();
            SystemLog::record('CREATE', 'Warehouse', 'Penerimaan manual: ' . $request->evidence_number);
            return redirect()->route('warehouse.inbound')->with('success', 'Penerimaan manual berhasil! Stok dan Jurnal bertambah.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function createOutbound() {
        $products = Product::where('stock_quantity', '>', 0)->orderBy('name', 'asc')->get();
        $accounts = Account::orderBy('account_code', 'asc')->get();
        $autoNumber = 'OUT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        return view('warehouse.create_outbound', compact('products', 'accounts', 'autoNumber'));
    }

    public function storeOutbound(Request $request) {
        $request->validate(['evidence_number' => 'required', 'transaction_date' => 'required|date', 'offset_account' => 'required', 'items' => 'required|array']);
        DB::beginTransaction();
        try {
            $now = now();
            $totalValue = 0;
            $ledgers = [];

            foreach ($request->items as $item) {
                if (isset($item['qty']) && $item['qty'] > 0) {
                    // FIX: Kunci baris produk dengan lockForUpdate
                    $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();
                    $qty = (int) $item['qty'];
                    if ($qty > $product->stock_quantity) throw new \Exception("Stok tidak cukup untuk " . $product->name);
                    
                    $cost = $product->average_cost;
                    $newStock = $product->stock_quantity - $qty;
                    $product->update(['stock_quantity' => $newStock]);
                    $lineTotal = $qty * $cost;
                    $totalValue += $lineTotal;

                    $ledgers[] = [
                        'transaction_date' => $request->transaction_date, 'evidence_number' => $request->evidence_number,
                        'product_id' => $product->id, 'type' => 'OUT', 'qty' => $qty, 'unit_cost' => $cost, 'total_cost' => $lineTotal,
                        'running_qty' => $newStock, 'running_value' => $newStock * $cost, 'moving_average_cost' => $cost,
                        'description' => $request->description ?? 'Barang Keluar Manual', 'created_at' => $now, 'updated_at' => $now
                    ];
                }
            }
            if ($totalValue > 0) {
                InventoryLedger::insert($ledgers);
                $jh = JournalHeader::create(['transaction_date' => $request->transaction_date, 'evidence_number' => $request->evidence_number, 'description' => $request->description ?? 'Barang Keluar Manual', 'source_doc_no' => $request->evidence_number, 'transaction_type' => 'Outbound']);
                JournalDetail::insert([
                    ['journal_id' => $jh->getKey(), 'account_code' => $request->offset_account, 'position' => 'DEBET', 'amount' => $totalValue, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                    ['journal_id' => $jh->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => $totalValue, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null]
                ]);
            }
            DB::commit();
            SystemLog::record('CREATE', 'Warehouse', 'Pengeluaran manual: ' . $request->evidence_number);
            return redirect()->route('warehouse.outbound')->with('success', 'Pengeluaran manual berhasil! Stok dan Jurnal berkurang.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
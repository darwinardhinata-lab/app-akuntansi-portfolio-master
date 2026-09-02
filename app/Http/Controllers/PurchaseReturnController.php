<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\Product;
use App\Models\InventoryLedger;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use Illuminate\Support\Facades\DB;
use App\Support\DocumentSequence;
use App\Services\InventorySyncService;

class PurchaseReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = PurchaseReturn::with('purchaseOrder')->orderBy('return_date', 'desc')->paginate(50);
        return view('purchase_return.index', compact('returns'));
    }

    public function create()
    {
        $pos = PurchaseOrder::whereIn('status', ['PARTIAL', 'RECEIVED'])->orderBy('transaction_date', 'desc')->get();
        $prefix = 'PR-' . date('Ymd') . '-';
        $autoNumber = DocumentSequence::preview('purchase_returns', 'return_number', $prefix);
        return view('purchase_return.create', compact('pos', 'autoNumber'));
    }

    // Endpoint AJAX untuk menarik rincian barang dari PO yang dipilih
    public function getPoItems($id)
    {
        $po = PurchaseOrder::with('details')->findOrFail($id);
        return response()->json(['items' => $po->details]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'return_date' => 'required|date',
            'items' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $prefix = 'PR-' . date('Ymd', strtotime($request->return_date)) . '-';
            $secureReturnNumber = DocumentSequence::generateSecure('purchase_returns', 'return_number', $prefix);

            $po = PurchaseOrder::findOrFail($request->purchase_order_id);
            $return = PurchaseReturn::create([
                'return_number' => $secureReturnNumber,
                'purchase_order_id' => $po->id,
                'return_date' => $request->return_date,
                'status' => 'COMPLETED', // Langsung potong stok
                'notes' => $request->notes ?? '-'
            ]);

            $totalReturnAmount = 0;
            $now = now();

            // OPTIMASI N+1 (N3): Preload semua PO detail & produk sekali saja,
            // bukan query per item (2 query per baris retur).
            $itemIds  = array_keys($request->items);
            $poDetails = PurchaseOrderDetail::whereIn('id', $itemIds)->get()->keyBy('id');
            $productIds = $poDetails->pluck('product_id')->filter()->unique()->toArray();
            $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // Collect items for centralized inventory sync (PR → OUT)
            $invItems = [];

            foreach ($request->items as $itemId => $qtyReturn) {
                $qty = (int) $qtyReturn;
                if ($qty <= 0) continue;

                $poDetail = $poDetails->get($itemId);
                if (!$poDetail) {
                    throw new \Exception("Detail PO #{$itemId} tidak ditemukan.");
                }
                $product = $products->get($poDetail->product_id);
                if (!$product) {
                    throw new \Exception("Produk untuk detail PO #{$itemId} tidak ditemukan.");
                }

                $lineTotal = $qty * $poDetail->price;
                $totalReturnAmount += $lineTotal;

                PurchaseReturnDetail::create([
                    'purchase_return_id' => $return->id,
                    'product_id' => $product->id,
                    'item_code' => $poDetail->item_code,
                    'description' => $poDetail->description,
                    'qty_returned' => $qty,
                    'price' => $poDetail->price,
                    'subtotal' => $lineTotal
                ]);

                // Kumpulkan item untuk InventorySyncService (PR → OUT)
                $invItems[] = [
                    'sku'       => $product->sku,
                    'qty'       => $qty,
                    'unit_cost' => $product->average_cost,
                ];
            }

            if ($totalReturnAmount <= 0) {
                throw new \Exception("Kuantitas barang yang diretur kosong.");
            }

            // ============================================================
            // INTEGRASI GUDANG (WAREHOUSE): PR → OUT (Stok Keluar)
            // ============================================================
            // Gunakan InventorySyncService untuk stock movements (PR → OUT)
            // yang akan bulk insert ke inventory_ledgers dan bulk update products.
            if (!empty($invItems)) {
                $inventoryService = new InventorySyncService();
                $inventoryService->processStockMovements(
                    $invItems,
                    $secureReturnNumber,
                    $request->return_date,
                    'PR',
                    "Retur Pembelian: {$secureReturnNumber} (Ref PO: {$po->po_number})",
                    true
                );
            }

            // Identifikasi Akun Hutang (AP) atau Uang Muka
            $noTransaksiPP = str_replace('PO-', '', $po->po_number);
            $paymentPlan = DB::table('transaksi_payment_plan')->where('no_transaksi', $noTransaksiPP)->where('kategori_payment', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')->first();
            $akunHutang = $paymentPlan ? config('coa.uang_muka_beli') : config('coa.hutang_usaha');

            // Jurnal Akuntansi (Debit: AP, Kredit: Persediaan)
            $journalHeader = JournalHeader::create([
                'transaction_date' => $request->return_date,
                'evidence_number' => $secureReturnNumber,
                'description' => "Retur Pembelian (Debit Note) Ref PO: {$po->po_number}",
                'source_doc_no' => $secureReturnNumber,
                'transaction_type' => 'Purchase Return',
            ]);

            $jDetails = [
                ['journal_id' => $journalHeader->getKey(), 'account_code' => $akunHutang, 'position' => 'DEBET', 'amount' => $totalReturnAmount, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
                ['journal_id' => $journalHeader->getKey(), 'account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => $totalReturnAmount, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null],
            ];

            if (!\App\Support\JournalBalanceValidator::isBalanced($jDetails)) {
                throw new \Exception("Jurnal retur tidak balance.");
            }

            JournalDetail::insert($jDetails);

            $return->update(['total_return_amount' => $totalReturnAmount]);

            DB::commit();
            return redirect()->route('purchase-returns.index')->with('success', 'SUKSES: Retur Pembelian berhasil dicatat! Stok Gudang berkurang dan Jurnal Pembalikan Hutang telah terposting.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
        }
    }
}
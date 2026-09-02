<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Models\SalesInvoice;
use App\Models\Product;
use App\Models\InventoryLedger;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use App\Support\DocumentSequence;
use App\Services\InventorySyncService;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = SalesReturn::with('invoice')->orderBy('created_at', 'desc')->paginate(50);
        return view('sales_return.index', compact('returns'));
    }

    public function create()
    {
        // Ambil daftar invoice unik
        $invoices = SalesInvoice::with('salesOrder')
            ->orderBy('transaction_date', 'desc')
            ->limit(300)
            ->get()
            ->map(function($inv) {
                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'transaction_date' => $inv->transaction_date,
                    'contact_name' => $inv->contact_name,
                ];
            });

        $prefix = 'SR-' . date('Ymd') . '-';
        $autoNumber = DocumentSequence::preview('sales_returns', 'return_number', $prefix);

        return view('sales_return.create', compact('invoices', 'autoNumber'));
    }

    public function getInvoiceItems($id)
    {
        $invoice = SalesInvoice::with('details.product')->findOrFail($id);
        $items = $invoice->details->map(function($det) {
            return [
                'id' => $det->id,
                'item_code' => $det->item_code,
                'description' => $det->description,
                'price' => (float) $det->price,
                'qty' => $det->qty_actual,
                'product_id' => $det->product_id,
            ];
        });

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'contact_name' => $invoice->contact_name,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_invoice_id' => 'required|exists:sales_invoices,id',
            'return_date' => 'required|date',
            'contact_name' => 'required|string',
            'details' => 'required|array|min:1',
            'details.*.item_code' => 'required|string',
            'details.*.qty_returned' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $prefix = 'SR-' . date('Ymd', strtotime($request->return_date)) . '-';
            $secureReturnNumber = DocumentSequence::generateSecure('sales_returns', 'return_number', $prefix);

            $return = SalesReturn::create([
                'return_number' => $secureReturnNumber,
                'sales_invoice_id' => $request->sales_invoice_id,
                'return_date' => $request->return_date,
                'status' => 'PENDING_INSPECTION',
            ]);

            foreach ($request->details as $det) {
                $product = Product::where('sku', $det['item_code'])->first();
                $qtyReturned = (int) ($det['qty_returned'] ?? 0);
                if ($qtyReturned <= 0) continue;

                SalesReturnDetail::create([
                    'sales_return_id' => $return->id,
                    'product_id' => $product ? $product->id : null,
                    'item_code' => $det['item_code'],
                    'description' => $det['description'] ?? ($product->name ?? ''),
                    'qty_returned' => $qtyReturned,
                    'qty_approved' => 0,
                    'condition' => 'GOOD',
                    'unit_price' => (float) ($det['price'] ?? 0),
                    'subtotal_refund' => 0,
                ]);
            }

            SystemLog::record('CREATE', 'Retur Penjualan', "Membuat retur penjualan: {$return->return_number}");

            DB::commit();
            return redirect()->route('sales-returns.show', $return->id)
                ->with('success', "Retur Penjualan {$return->return_number} berhasil dibuat. Silakan lakukan pemeriksaan gudang.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan retur. ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $return = SalesReturn::with(['details.product', 'invoice.details'])->findOrFail($id);
        return view('sales_return.show', compact('return'));
    }

    public function process(Request $request, $id)
    {
        $return = SalesReturn::with(['details', 'invoice.details'])->findOrFail($id);

        // 1. SAFETY NET: Prevent double processing
        if ($return->status !== 'PENDING_INSPECTION') {
            return redirect()->route('sales-returns.index')->with('error', 'Gagal: Dokumen retur ini sudah diproses sebelumnya!');
        }

        $decision = $request->input('decision');
        $notes = $request->input('notes');

        DB::beginTransaction();
        try {
            $return->update([
                'status' => $decision,
                'inspected_by' => auth()->user()->name ?? 'Tim Gudang',
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            if ($decision === 'REJECT') {
                SystemLog::record('UPDATE', 'Retur Penjualan', "Menolak retur: {$return->return_number}");
                DB::commit();
                return redirect()->route('sales-returns.index')->with('success', 'Pemeriksaan selesai: Retur DITOLAK. Piutang dan stok tidak berubah.');
            }

            $refundShipping = (float) $request->input('refund_shipping_cost', 0);
            $returnShipping = (float) $request->input('return_shipping_cost', 0);

            $totalRefundGoods = 0;
            $totalCogsGood = 0;
            $totalCogsDefective = 0;
            $now = now();

            // FIX N+1: load products & ledgers once before loop
            $productIds = $return->details->pluck('product_id')->filter()->unique()->all();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $ledgersOut = InventoryLedger::where('evidence_number', $return->invoice->invoice_number)
                ->where('type', 'OUT')
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            // Collect items for centralized inventory sync (SR → IN)
            $invItems = [];

            foreach ($return->details as $det) {
                $qtyApproved = (int) ($request->input("items.{$det->id}.qty_approved") ?? $det->qty_returned);
                $condition = $request->input("items.{$det->id}.condition") ?? 'GOOD';

                $invDetail = $return->invoice->details->where('product_id', $det->product_id)->first();
                $unitPrice = $invDetail ? $invDetail->price : 0;

                $ledgerOut = $ledgersOut->get($det->product_id);
                
                $product = $products->get($det->product_id);
                $originalCogs = $ledgerOut ? $ledgerOut->unit_cost : ($product->average_cost ?? 0);

                $subtotalRefund = $qtyApproved * $unitPrice;

                $det->update([
                    'qty_approved' => $qtyApproved,
                    'condition' => $condition,
                    'unit_price' => $unitPrice,
                    'cogs_value' => $originalCogs,
                    'subtotal_refund' => $subtotalRefund
                ]);

                $totalRefundGoods += $subtotalRefund;
                
                // FIX: Pisahkan perhitungan HPP barang bagus dan cacat
                if ($condition === 'GOOD') {
                    $totalCogsGood += ($qtyApproved * $originalCogs);
                    // Kumpulkan item untuk InventorySyncService (SR → IN)
                    if ($qtyApproved > 0 && $product) {
                        $invItems[] = [
                            'sku'       => $product->sku,
                            'qty'       => $qtyApproved,
                            'unit_cost' => $originalCogs,
                        ];
                    }
                } else {
                    $totalCogsDefective += ($qtyApproved * $originalCogs);
                }
            }

            // ============================================================
            // INTEGRASI GUDANG (WAREHOUSE): SR → IN (Stok Masuk)
            // ============================================================
            // Gunakan InventorySyncService untuk stock movements (SR → IN)
            // yang akan bulk insert ke inventory_ledgers dan bulk update products.
            if (!empty($invItems)) {
                $inventoryService = new InventorySyncService();
                $inventoryService->processStockMovements(
                    $invItems,
                    $return->return_number,
                    $now->toDateString(),
                    'SR',
                    "Retur Penjualan ({$decision}): {$return->return_number}",
                    true
                );
            }

            $totalRefundAmount = $totalRefundGoods + $refundShipping;

            $return->update([
                'refund_shipping_cost' => $refundShipping,
                'return_shipping_cost' => $returnShipping,
                'total_refund_amount'  => $totalRefundAmount
            ]);

            $journalId = JournalHeader::generateNextId();
            $jHeader = JournalHeader::create([
                'journal_id'       => $journalId,
                'transaction_date' => $now->toDateString(),
                'evidence_number'  => $return->return_number,
                'description'      => "Retur Penjualan: {$return->return_number} (Ref: {$return->invoice->invoice_number})",
                'sales_ret_id'     => $return->id,
                'transaction_type' => 'Sales Return',
                'tags'             => $return->invoice->salesOrder->store_name ?? 'Pusat'
            ]);

            $jDetails = [];

            if ($totalRefundGoods > 0) {
                // Retur Penjualan Umum
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.retur_penjualan'), 'position' => 'DEBET', 'amount' => $totalRefundGoods, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($refundShipping > 0) {
                // Memotong Pendapatan Ongkir
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.ongkos_kirim'), 'position' => 'DEBET', 'amount' => $refundShipping, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($totalRefundAmount > 0) {
                // Piutang Berkurang
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.piutang_usaha'), 'position' => 'KREDIT', 'amount' => $totalRefundAmount, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($totalCogsGood > 0) {
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.persediaan'), 'position' => 'DEBET', 'amount' => $totalCogsGood, 'created_at' => $now, 'updated_at' => $now];
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.hpp'), 'position' => 'KREDIT', 'amount' => $totalCogsGood, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($totalCogsDefective > 0) {
                // Penyesuaian Persediaan Barang (-)
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.kerugian_barang_cacat'), 'position' => 'DEBET', 'amount' => $totalCogsDefective, 'created_at' => $now, 'updated_at' => $now];
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.hpp'), 'position' => 'KREDIT', 'amount' => $totalCogsDefective, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($returnShipping > 0) {
                // Biaya Kirim Retur / COD
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.biaya_kirim'), 'position' => 'DEBET', 'amount' => $returnShipping, 'created_at' => $now, 'updated_at' => $now];
                // Hutang Usaha / Beban Titipan 
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.hutang_usaha'), 'position' => 'KREDIT', 'amount' => $returnShipping, 'created_at' => $now, 'updated_at' => $now];
            }

            if (!empty($jDetails)) {
                JournalDetail::insert($jDetails);
            }

            SystemLog::record('UPDATE', 'Retur Penjualan', "Menyelesaikan proses retur {$return->return_number} dengan status {$decision}.");
            
            DB::commit();
            return redirect()->route('sales-returns.index')->with('success', "SUKSES! Retur {$return->return_number} berhasil difinalisasi. Jurnal akuntansi dan Kartu Stok telah disinkronkan.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'FATAL ERROR: Gagal memproses retur. ' . $e->getMessage());
        }
    }
}
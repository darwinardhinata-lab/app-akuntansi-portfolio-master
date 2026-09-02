<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillDetail;
use App\Models\Account;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Services\InventorySyncService;

class PurchaseBillController extends Controller
{
    public function dispatchSyncJob()
    {
        try {
            // Dispatch tahap 1
            \App\Jobs\SyncBillDashboardToTempJob::dispatch();
            
            return back()->with('success', 'Proses sinkronisasi Bills dari Dashboard sedang berjalan di background (Tahap 1 & 2).');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Gagal dispatch Bill Sync: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memulai sinkronisasi: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $bills = PurchaseBill::when($search, function($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%");
            })->orderBy('transaction_date', 'desc')->paginate(50);

        return view('purchase_bill.index', compact('bills', 'search'));
    }

    public function create()
    {
        $accounts = Account::orderBy('account_code', 'asc')->get();
        // Hanya tampilkan akun Kas/Bank dan Hutang untuk sumber pembayaran
        $creditAccounts = Account::where(function($q) {
            $q->where(DB::raw('LEFT(account_code, 1)'), '1')
              ->orWhere(DB::raw('LEFT(account_code, 1)'), '2');
        })->orderBy('account_code', 'asc')->get();

        $prefix = 'BIL-' . date('Ymd') . '-';
        $autoNumber = DocumentSequence::preview('purchase_bills', 'bill_number', $prefix);
        return view('purchase_bill.create', compact('accounts', 'creditAccounts', 'autoNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'bill_date' => 'required|date',
            'vendor_name' => 'required|string',
            'credit_account' => 'required|string',
            'details' => 'required|array|min:1',
            // Optional item-level details for inventory sync (BIL → IN)
            'items' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $prefix = 'BIL-' . date('Ymd', strtotime($request->bill_date)) . '-';
            $secureBillNumber = DocumentSequence::generateSecure('purchase_bills', 'bill_number', $prefix);

            $subTotal = 0;
            foreach ($request->details as $det) {
                $subTotal += (float) ($det['amount'] ?? 0);
            }
            $taxAmount = (float) ($request->tax_amount ?? 0);
            $grandTotal = $subTotal + $taxAmount;

            $bill = PurchaseBill::create([
                'bill_number' => $secureBillNumber,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'vendor_name' => $request->vendor_name,
                'sub_total' => $subTotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'credit_account' => $request->credit_account,
                'notes' => $request->notes,
                'payment_status' => (substr($request->credit_account, 0, 1) == '1') ? 'PAID' : 'UNPAID',
            ]);

            $detailsToInsert = [];
            foreach ($request->details as $det) {
                $amt = (float) ($det['amount'] ?? 0);
                if ($amt > 0) {
                    $detailsToInsert[] = [
                        'purchase_bill_id' => $bill->id,
                        'account_code' => $det['account_code'],
                        'description' => $det['description'] ?? '-',
                        'amount' => $amt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            PurchaseBillDetail::insert($detailsToInsert);

            // Jurnal Akuntansi Otomatis
            $journalId = null;
            if ($grandTotal > 0) {
                $journalHeader = JournalHeader::create([
                    'transaction_date' => $request->bill_date,
                    'evidence_number' => $bill->bill_number,
                    'description' => "Tagihan Pembelian: {$bill->vendor_name} (" . $request->notes . ")",
                    'source_doc_no' => $bill->bill_number,
                    'transaction_type' => 'Purchase Bill',
                ]);

                $journalId = $journalHeader->getKey();
                $now = now();
                $jDetails = [];

                // Debet: Akun Biaya / Persediaan per baris
                foreach ($detailsToInsert as $d) {
                    $jDetails[] = ['journal_id' => $journalId, 'account_code' => $d['account_code'], 'position' => 'DEBET', 'amount' => $d['amount'], 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null];
                }

                // Debet: Pajak (Jika ada PPN Masukan)
                if ($taxAmount > 0) {
                    $jDetails[] = ['journal_id' => $journalId, 'account_code' => config('coa.pajak_masukan'), 'position' => 'DEBET', 'amount' => $taxAmount, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null];
                }

                // Kredit: Hutang / Kas Bank
                $jDetails[] = ['journal_id' => $journalId, 'account_code' => $request->credit_account, 'position' => 'KREDIT', 'amount' => $grandTotal, 'created_at' => $now, 'updated_at' => $now, 'helper_code' => null];

                if (!JournalBalanceValidator::isBalanced($jDetails)) {
                    throw new \Exception("Jurnal tagihan pembelian tidak balance. Silakan periksa konfigurasi pajak dan total.");
                }

                JournalDetail::insert($jDetails);
            }

            // ============================================================
            // INTEGRASI GUDANG (WAREHOUSE): BIL → IN (Stok Masuk)
            // ============================================================
            // Jika ada item-level details (SKU, qty, unit_cost),
            // sinkronkan ke inventory_ledgers dan products.
            $items = $request->input('items', []);
            if (!empty($items)) {
                $inventoryService = new InventorySyncService();
                $inventoryService->processStockMovements(
                    $items,
                    $bill->bill_number,
                    $request->bill_date,
                    'BIL',
                    "Tagihan Pembelian: {$bill->vendor_name} ({$bill->bill_number})",
                    true
                );
            }

            // Link journal_id ke Purchase Bill (FK)
            if ($journalId) {
                $bill->update(['journal_id' => $journalId]);
            }

            DB::commit();
            SystemLog::record('CREATE', 'Purchase Bill', 'Membuat tagihan manual: ' . $bill->bill_number);
            return redirect()->route('purchase-bills.index')->with('success', 'Tagihan Pembelian (Bill) berhasil dicatat dan dijurnal otomatis!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal memproses tagihan: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $bill = PurchaseBill::findOrFail($id);

            // 1. Reverse stock movements (BIL → IN) jika ada inventory ledger
            $inventoryService = new InventorySyncService();
            $inventoryService->reverseStockMovements($bill->bill_number, 'BIL');

            // 2. Hapus Jurnal
            $journalIds = JournalHeader::where('evidence_number', $bill->bill_number)->pluck('journal_id');
            if ($journalIds->isNotEmpty()) {
                JournalDetail::whereIn('journal_id', $journalIds)->delete();
                JournalHeader::whereIn('journal_id', $journalIds)->delete();
            }

            // 3. Hapus Bill
            $bill->delete();

            DB::commit();
            SystemLog::record('DELETE', 'Purchase Bill', 'Membatalkan tagihan: ' . $bill->bill_number);
            return redirect()->route('purchase-bills.index')->with('success', 'Tagihan berhasil dihapus. Stok Gudang, Kartu Stok, dan Jurnal Keuangan otomatis dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus tagihan: ' . $e->getMessage());
        }
    }
}
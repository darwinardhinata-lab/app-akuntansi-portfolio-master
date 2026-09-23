<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\PurchaseReturn;
use App\Models\PurchaseBill;
use App\Models\JournalHeader;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\MaterialReceipt;

class DocumentTraceController extends Controller
{
    /**
     * Membaca prefix nomor bukti, mencari dokumen aslinya, lalu me-redirect user.
     * Ini adalah "Smart Redirector" untuk navigasi antar-modul ERP.
     */
    public function trace($evidence_number)
    {
        $evidence = trim($evidence_number);
        
        // Handle empty evidence number
        if (empty($evidence)) {
            return back()->with('error', 'Nomor bukti tidak valid atau kosong.');
        }
        
        // 1. Deteksi Awalan (Prefix) Dokumen
        $prefix = strtoupper(explode('-', $evidence)[0] ?? '');
        
        switch ($prefix) {
            // --- MODUL PEMBELIAN (PURCHASE) ---
            case 'PO': 
                $doc = PurchaseOrder::where('po_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('po.edit', $doc->id);
                }
                break;

            // --- MODUL PENJUALAN (SALES ORDER) ---
            case 'SO':
                $doc = SalesOrder::where('so_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('so.edit', $doc->id);
                }
                break;

            // --- MODUL FAKTUR PENJUALAN (INVOICE) ---
            case 'INV':
                // Cari di SalesInvoice berdasarkan invoice_number
                $doc = SalesInvoice::where('invoice_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('invoice.show', $doc->id);
                }
                break;

            // --- MODUL PENJUALAN POS (Point of Sales) ---
            case 'POS':
                // POS mengarah ke SalesOrder dengan format khusus
                $doc = SalesOrder::where('so_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('so.edit', $doc->id);
                }
                break;

            // --- MODUL RETUR ---
            case 'SR':
                // Sales Return - format SR-XXXX
                $doc = SalesReturn::where('return_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('sales-returns.show', $doc->id);
                }
                break;
                
            case 'PR':
                // Purchase Return - format PR-XXXX
                $doc = PurchaseReturn::where('return_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('purchase-returns.show', $doc->id);
                }
                break;

            // --- MODUL TAGIHAN PEMBELIAN ---
            case 'BIL':
                $doc = PurchaseBill::where('bill_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('purchase-bills.show', $doc->id);
                }
                break;

            // --- MODUL MANUFAKTUR (PABRIK) ---
            // [BARU] Diaktifkan penuh sebagai bagian dari integrasi Anthrilo Manufacturing.
            // Format: MFG-YYYYMMDD-#### (spk_number pada tabel mfg_work_orders).
            case 'MFG':
            case 'SPK':
                $wo = WorkOrder::where('spk_number', $evidence)->first();
                if ($wo) {
                    return redirect()->route('mfg.work-orders.show', $wo->id);
                }
                // Fallback: mungkin evidence adalah jurnal penyelesaian WIP->Barang Jadi
                $wo = WorkOrder::where('journal_id', $evidence)->first();
                if ($wo) {
                    return redirect()->route('mfg.work-orders.show', $wo->id);
                }
                return redirect()->route('mfg.work-orders.index', ['search' => $evidence])
                    ->with('info', "Dokumen {$evidence} tidak ditemukan persis, menampilkan pencarian SPK.");

            // --- MODUL MANUFAKTUR: MRN (Material Receipt Note bahan baku) ---
            // [BARU] Format: MRN-YYYYMMDD-#### (receipt_number pada tabel mfg_material_receipts).
            case 'MRN':
                $mrn = MaterialReceipt::where('receipt_number', $evidence)->first();
                if ($mrn) {
                    return redirect()->route('mfg.material-receipts.show', $mrn->id);
                }
                break;

            // --- MODUL SETUP (SALDO AWAL) ---
            case 'SA':
            case 'OB':
                return back()->with('info', 'Dokumen ' . $evidence . ' adalah Setup Saldo Awal (Opening Balance). Tidak ada dokumen sumber fisik.');

            // --- JURNAL MANUAL (GJ / GJ-XXXX) ---
            case 'GJ':
                $journal = JournalHeader::where('evidence_number', $evidence)->first();
                if ($journal) {
                    return back()->with('info', "Dokumen {$evidence} adalah Jurnal Manual (General Journal). Transaksi ini diinput langsung ke sistem akuntansi tanpa dokumen operasional (PO/SO/Invoice) sebagai sumber.");
                }
                break;

            // --- PREFIX SINKRONISASI DATA EKSTERNAL / TRANSAKSI KHUSUS TANPA MODUL FISIK ERP ---
            case 'ADJ':
            case 'REFF':
            case 'RET':   // Pengembalian Uang Pemasok
            case 'KSY':   // Penerimaan Konsinyasi
            case 'CP':
            case 'SP':
            case 'AR':
            case 'AP':
            case 'BR':
            case 'CD':
            case 'DP':
                $journal = JournalHeader::where('evidence_number', $evidence)->first();
                if ($journal) {
                    return redirect()->route('jurnal.index', ['search' => $evidence])
                        ->with('info', "Dokumen {$evidence} belum punya modul operasional tersendiri di ERP ini (data hanya tersedia sebagai jurnal hasil sinkronisasi data eksternal).");
                }
                break;

            // --- DEFAULT / JURNAL MANUAL (JRN) ---
            case 'JRN':
            case 'PP': // Payment Plan
                // Jika tidak dikenali atau JURNAL, arahkan ke detail Jurnal Umum itu sendiri
                $journal = JournalHeader::where('journal_id', $evidence)->first();
                if ($journal) {
                    return redirect()->route('jurnal.edit', $journal->journal_id);
                }
                break;

            default:
                // Coba cari di semua tabel secara berurutan
                // Purchase Order
                $doc = PurchaseOrder::where('po_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('po.edit', $doc->id);
                }
                
                // Sales Order
                $doc = SalesOrder::where('so_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('so.edit', $doc->id);
                }
                
                // Sales Invoice
                $doc = SalesInvoice::where('invoice_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('invoice.show', $doc->id);
                }
                
                // Purchase Bill
                $doc = PurchaseBill::where('bill_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('purchase-bills.show', $doc->id);
                }
                
                // Sales Return
                $doc = SalesReturn::where('return_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('sales-returns.show', $doc->id);
                }
                
                // Purchase Return
                $doc = PurchaseReturn::where('return_number', $evidence)->first();
                if ($doc) {
                    return redirect()->route('purchase-returns.show', $doc->id);
                }
                
                // Jika tidak ditemukan di semua tabel, arahkan ke jurnal
                $journal = JournalHeader::where('evidence_number', $evidence)->first();
                if ($journal) {
                    return redirect()->route('jurnal.edit', $journal->journal_id);
                }
                break;
        }

        // Jika dokumen benar-benar tidak ditemukan (mungkin sudah dihapus/purged)
        return back()->with('error', "Dokumen referensi dengan nomor {$evidence} tidak ditemukan di sistem atau mungkin telah dihapus.");
    }
}

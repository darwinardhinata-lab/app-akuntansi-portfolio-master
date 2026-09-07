<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\MaterialReceipt;
use App\Modules\Manufacturing\Models\MaterialReceiptDetail;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * JURNAL #1 (lihat MANUFACTURING_INTEGRATION.md §3):
 *   Debit  Persediaan Bahan Baku Benang/Kain (config('coa.persediaan_bahan_baku_benang'/'_kain'))
 *   Kredit Hutang Usaha Maklun (config('coa.hutang_usaha_maklun'))
 *
 * Sumber logic: mapping dari Anthrilo `gate_entries` + `mrns` + `mrn_items`
 * (disederhanakan jadi 1 dokumen MRN, lihat §2 MANUFACTURING_INTEGRATION.md).
 */
class MaterialReceiptService
{
    /**
     * Membuat MRN baru dari data mentah (dipanggil Controller Stage 3),
     * lalu langsung posting jurnal + kartu stok bahan baku.
     *
     * @param array $header  ['receipt_date','supplier_id','po_id','supplier_doc_no','supplier_doc_date','tax_amount','remarks','created_by']
     * @param array $items   list of ['item_type','yarn_id'|'fabric_id','item_name','qty','unit','rate','lot_number','po_detail_id']
     */
    public function createAndPost(array $header, array $items): MaterialReceipt
    {
        if (empty($items)) {
            throw new Exception('MRN harus memiliki minimal 1 item bahan baku.');
        }

        return DB::transaction(function () use ($header, $items) {
            $now = now();
            $receiptNumber = DocumentSequence::generateSecure(
                'mfg_material_receipts', 'receipt_number', 'MRN-' . now()->format('Ymd') . '-'
            );

            // Lock semua item bahan baku yang terlibat SEBELUM hitung apa pun,
            // agar tidak terjadi lost-update saat MRN paralel menyentuh yarn/fabric yang sama.
            $yarnIds   = collect($items)->where('item_type', 'YARN')->pluck('yarn_id')->filter()->unique()->all();
            $fabricIds = collect($items)->where('item_type', 'FABRIC')->pluck('fabric_id')->filter()->unique()->all();
            $yarns     = $yarnIds ? Yarn::whereIn('id', $yarnIds)->lockForUpdate()->get()->keyBy('id') : collect();
            $fabrics   = $fabricIds ? Fabric::whereIn('id', $fabricIds)->lockForUpdate()->get()->keyBy('id') : collect();

            $grossAmount = 0;
            $costByAccount = []; // [account_code => total] utk Debit jurnal (yarn vs kain dipisah akun)
            $detailRows = [];

            foreach ($items as $row) {
                $qty  = (float) $row['qty'];
                $rate = (float) $row['rate'];
                $amount = $qty * $rate;
                $grossAmount += $amount;

                // RULES.md §5 (pola "H2 FIX" PurchaseOrderService) — Validasi Qty Terima:
                // qtyTerima <= (qtyPO - qtyReceivedSebelumnya), jika MRN ini berasal dari PO.
                if (!empty($row['po_detail_id'])) {
                    $poDetail = DB::table('mfg_material_purchase_order_details')->where('id', $row['po_detail_id'])->lockForUpdate()->first();
                    if ($poDetail) {
                        $sisaBolehTerima = (float) $poDetail->qty - (float) $poDetail->qty_received;
                        if ($qty > $sisaBolehTerima) {
                            throw new Exception(
                                "Qty terima '{$row['item_name']}' ({$qty}) melebihi sisa PO yang belum diterima ({$sisaBolehTerima}). " .
                                "Cek kembali PO Detail ID {$row['po_detail_id']}."
                            );
                        }
                    }
                }

                if ($row['item_type'] === 'YARN') {
                    $yarn = $yarns->get($row['yarn_id']) ?? throw new Exception("Yarn ID {$row['yarn_id']} tidak ditemukan.");
                    $result = MaterialCostHelper::receiveStock(
                        $yarn, 'YARN', $qty, $rate, $header['receipt_date'], $receiptNumber,
                        "Penerimaan MRN: {$receiptNumber} - {$row['item_name']}"
                    );
                    $accountCode = $yarn->inventory_account_code ?: config('coa.persediaan_bahan_baku_benang');
                } else {
                    $fabric = $fabrics->get($row['fabric_id']) ?? throw new Exception("Fabric ID {$row['fabric_id']} tidak ditemukan.");
                    $result = MaterialCostHelper::receiveStock(
                        $fabric, 'FABRIC', $qty, $rate, $header['receipt_date'], $receiptNumber,
                        "Penerimaan MRN: {$receiptNumber} - {$row['item_name']}"
                    );
                    $accountCode = $fabric->inventory_account_code ?: config('coa.persediaan_bahan_baku_kain');
                }

                $costByAccount[$accountCode] = ($costByAccount[$accountCode] ?? 0) + $amount;

                $detailRows[] = [
                    'po_detail_id' => $row['po_detail_id'] ?? null,
                    'item_type'    => $row['item_type'],
                    'yarn_id'      => $row['yarn_id'] ?? null,
                    'fabric_id'    => $row['fabric_id'] ?? null,
                    'item_name'    => $row['item_name'],
                    'qty'          => $qty,
                    'unit'         => $row['unit'] ?? 'KGS',
                    'rate'         => $rate,
                    'amount'       => $amount,
                    'lot_number'   => $row['lot_number'] ?? null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            $taxAmount = (float) ($header['tax_amount'] ?? 0);
            $netAmount = $grossAmount + $taxAmount;

            // Anti-dobel posting: 1 nomor evidence hanya boleh 1 jurnal utk transaction_type ini.
            $existing = JournalHeader::where('evidence_number', $receiptNumber)
                ->where('transaction_type', 'Material Receipt (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor MRN '{$receiptNumber}' sudah memiliki jurnal. Kemungkinan race condition, ulangi proses.");
            }

            $journal = JournalHeader::create([
                'transaction_date' => $header['receipt_date'],
                'evidence_number'  => $receiptNumber,
                'description'      => "Penerimaan Bahan Baku (MRN) dari Supplier ID {$header['supplier_id']}",
                'transaction_type' => 'Material Receipt (MFG)',
            ]);

            $journalRows = [];
            foreach ($costByAccount as $accountCode => $amount) {
                $journalRows[] = [
                    'journal_id'   => $journal->getKey(),
                    'account_code' => $accountCode,
                    'helper_code'  => null,
                    'position'     => 'DEBET',
                    'amount'       => $amount,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            $journalRows[] = [
                'journal_id'   => $journal->getKey(),
                'account_code' => config('coa.hutang_usaha_maklun'),
                'helper_code'  => null, // TODO Stage 3: isi dgn helper_code supplier jika sudah terdaftar di helper_codes
                'position'     => 'KREDIT',
                'amount'       => $netAmount,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
            if ($taxAmount > 0) {
                // Pajak masukan dipisah sbg baris Debit tersendiri (bukan ikut nilai persediaan)
                $journalRows[] = [
                    'journal_id'   => $journal->getKey(),
                    'account_code' => config('coa.pajak_masukan'),
                    'helper_code'  => null,
                    'position'     => 'DEBET',
                    'amount'       => $taxAmount,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            // RULES.md §2 — Double-Entry Validation: WAJIB DEBET = KREDIT sebelum insert.
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception('Jurnal MRN tidak balance (Debet != Kredit). Batalkan & cek input.');
            }
            JournalDetail::insert($journalRows);

            $receipt = MaterialReceipt::create([
                'receipt_number'    => $receiptNumber,
                'receipt_date'      => $header['receipt_date'],
                'supplier_id'       => $header['supplier_id'],
                'po_id'             => $header['po_id'] ?? null,
                'supplier_doc_no'   => $header['supplier_doc_no'] ?? null,
                'supplier_doc_date' => $header['supplier_doc_date'] ?? null,
                'gross_amount'      => $grossAmount,
                'tax_amount'        => $taxAmount,
                'net_amount'        => $netAmount,
                'status'            => 'POSTED',
                'journal_id'        => $journal->getKey(),
                'remarks'           => $header['remarks'] ?? null,
                'created_by'        => $header['created_by'] ?? null,
            ]);

            foreach ($detailRows as $row) {
                $row['receipt_id'] = $receipt->id;
            }
            MaterialReceiptDetail::insert($detailRows);

            // Update qty_received di PO bahan baku terkait (jika MRN berasal dari PO)
            if (!empty($header['po_id'])) {
                foreach ($items as $row) {
                    if (!empty($row['po_detail_id'])) {
                        DB::table('mfg_material_purchase_order_details')
                            ->where('id', $row['po_detail_id'])
                            ->increment('qty_received', $row['qty']);
                    }
                }
            }

            return $receipt;
        });
    }

    /**
     * STAGE 5 — Void MRN: membalik jurnal + kartu stok bahan baku, mengikuti
     * pola persis PurchaseOrderService::voidReceipt() (lock, hapus jurnal by
     * evidence_number, kurangi kembali stok & average_cost, hapus baris ledger).
     *
     * PEMBATASAN PENTING: hanya bisa void MRN yang stoknya BELUM terpakai
     * sama sekali di tahap berikutnya (yarn issue / fabric issue / cutting).
     * Ini untuk mencegah average_cost yang sudah dipakai di transaksi lain
     * jadi tidak konsisten. Jika stok sudah terpakai sebagian, tolak void dan
     * arahkan user membuat jurnal koreksi manual.
     */
    public function void(int $receiptId): bool
    {
        return DB::transaction(function () use ($receiptId) {
            $receipt = MaterialReceipt::with('details.yarn', 'details.fabric')->lockForUpdate()->findOrFail($receiptId);

            if ($receipt->status === 'VOIDED') {
                throw new Exception("MRN '{$receipt->receipt_number}' sudah berstatus VOIDED.");
            }

            foreach ($receipt->details as $detail) {
                $item = $detail->item_type === 'YARN' ? $detail->yarn : $detail->fabric;
                if (!$item) continue;

                // Cek apakah qty yang diterima MRN ini sudah terpakai (stok saat ini < qty MRN
                // berarti sebagian sudah keluar lagi via issue/cutting sejak MRN ini diposting).
                if ((float) $item->stock_quantity < (float) $detail->qty) {
                    throw new Exception(
                        "Tidak bisa void: stok {$detail->item_name} sudah terpakai sebagian sejak MRN ini diposting. " .
                        "Buat jurnal koreksi manual di menu Jurnal Umum, atau hubungi admin."
                    );
                }
            }

            // Aman untuk dibalik: kurangi qty & value, ledger IN dihapus.
            foreach ($receipt->details as $detail) {
                $item = $detail->item_type === 'YARN' ? $detail->yarn : $detail->fabric;
                if (!$item) continue;

                $currentValue = (float) $item->stock_quantity * (float) $item->average_cost;
                $newStock = (float) $item->stock_quantity - (float) $detail->qty;
                $newValue = $currentValue - (float) $detail->amount;
                $newMac = $newStock > 0 ? max(0, $newValue / $newStock) : 0;

                $item->stock_quantity = $newStock;
                $item->average_cost = $newMac;
                $item->save();
            }

            MaterialLedger::where('evidence_number', $receipt->receipt_number)->delete();

            if ($receipt->journal_id) {
                JournalDetail::where('journal_id', $receipt->journal_id)->delete();
                JournalHeader::where('journal_id', $receipt->journal_id)->delete();
            }

            $receipt->status = 'VOIDED';
            $receipt->journal_id = null;
            $receipt->save();

            return true;
        });
    }
}

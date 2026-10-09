<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\MaterialReceipt;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\MaterialReceiptDetail;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use App\Modules\Platform\Support\CompanyCoaResolver;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * JURNAL #1 (lihat MANUFACTURING_INTEGRATION.md §3):
 *   Debit  Persediaan Bahan Baku (mapping MGI: 114003)
 *   Kredit Utang Usaha (mapping MGI: 211001)
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
            \App\Support\AccountingPeriodGuard::source([$header['receipt_date'] ?? null]);
            // FIX: periksa semua referensi PO sebelum resolusi COA dan penulisan stok/jurnal.
            $orderIds = [];
            if (!empty($header['po_id'])) {
                $orderIds[] = (int) $header['po_id'];
            }

            $detailIds = collect($items)->pluck('po_detail_id')->filter()->unique()->values();
            if ($detailIds->isNotEmpty()) {
                $details = DB::table('mfg_material_purchase_order_details')
                    ->whereIn('id', $detailIds)->get(['id', 'po_id']);
                if ($details->count() !== $detailIds->count()) {
                    throw new Exception('Referensi detail Material PO tidak ditemukan.');
                }
                foreach ($details as $detail) {
                    if (!empty($header['po_id']) && (int) $detail->po_id !== (int) $header['po_id']) {
                        throw new Exception('Detail Material PO tidak sesuai dengan PO penerimaan.');
                    }
                    $orderIds[] = (int) $detail->po_id;
                }
            }

            // FIX: urutan lock numerik konsisten untuk receipt yang merujuk lebih dari satu PO.
            $orderIds = array_unique($orderIds);
            sort($orderIds, SORT_NUMERIC);
            foreach ($orderIds as $orderId) {
                $order = MaterialPurchaseOrder::whereKey($orderId)->lockForUpdate()->first();
                if (!$order) {
                    throw new Exception('Material PO penerimaan tidak ditemukan.');
                }
                if ($order->approval_status !== MaterialPurchaseOrder::APPROVED) {
                    throw new Exception("Material PO {$order->po_number} belum APPROVED; penerimaan barang ditolak (po_id {$order->id}).");
                }
                if (!in_array($order->status, ['APPROVED', 'PARTIAL'], true)) {
                    throw new Exception("Material PO {$order->po_number} tidak terbuka untuk penerimaan barang.");
                }
                if ((int) $order->supplier_id !== (int) ($header['supplier_id'] ?? 0)) {
                    throw new Exception('Supplier penerimaan tidak sesuai dengan Material PO.');
                }
            }

            foreach ($items as $row) {
                if (!empty($header['po_id']) && empty($row['po_detail_id'])) {
                    throw new Exception('po_detail_id wajib untuk setiap item penerimaan dengan po_id.');
                }
                if (empty($header['po_id']) && !empty($row['po_detail_id'])) {
                    throw new Exception('po_id wajib jika item memiliki po_detail_id.');
                }
            }
            if (!empty($header['po_id'])) {
                $lockedDetails = DB::table('mfg_material_purchase_order_details')->whereIn('id', $detailIds)
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $requested = [];
                foreach ($items as $row) {
                    $detail = $lockedDetails->get($row['po_detail_id']);
                    $type = $row['item_type'] ?? '';
                    $idField = ['YARN' => 'yarn_id', 'FABRIC' => 'fabric_id', 'AUXILIARY' => 'auxiliary_material_id'][$type] ?? null;
                    if (!$idField || $detail->item_type !== $type
                        || (int) ($row[$idField] ?? 0) !== (int) $detail->$idField
                        || ($row['unit'] ?? null) !== $detail->unit) {
                        throw new Exception('Identitas material atau unit tidak sesuai dengan detail Material PO.');
                    }
                    $qty = (string) ($row['qty'] ?? '');
                    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $qty) || bccomp($qty, '0', 2) <= 0) {
                        throw new Exception('Qty penerimaan harus positif dengan maksimal 2 desimal.');
                    }
                    $requested[$detail->id] = bcadd($requested[$detail->id] ?? '0', $qty, 2);
                }
                foreach ($requested as $id => $quantity) {
                    $detail = $lockedDetails->get($id);
                    $remaining = bcsub((string) $detail->qty, (string) $detail->qty_received, 2);
                    if (bccomp($quantity, $remaining, 2) > 0) {
                        throw new Exception("Material PO {$order->po_number}: qty terima ".number_format((float) $quantity, 2, '.', '')
                            .' melebihi sisa PO '.number_format((float) $remaining, 2, '.', '')." (po_detail_id {$id}).");
                    }
                }
            }

            $now = now();
            // Material procurement is a manufacturing flow. It must not fall
            // back to legacy 11210/11220/22010 codes that are not part of the
            // approved MGI COA mapping.
            $company = app(OperationalCompany::class)->company();
            $coa = app(CompanyCoaResolver::class);
            $rawMaterialAccount = null;
            $auxiliaryMaterialAccount = null;
            $accountsPayableAccount = $coa->account($company, 'accounts_payable');
            $inputVatAccount = $coa->account($company, 'input_vat');
            $receiptNumber = DocumentSequence::generateSecure(
                'mfg_material_receipts', 'receipt_number', 'MRN-' . now()->format('Ymd') . '-'
            );

            // Lock semua item bahan baku yang terlibat SEBELUM hitung apa pun,
            // agar tidak terjadi lost-update saat MRN paralel menyentuh yarn/fabric yang sama.
            $yarnIds   = collect($items)->where('item_type', 'YARN')->pluck('yarn_id')->filter()->unique()->all();
            $fabricIds = collect($items)->where('item_type', 'FABRIC')->pluck('fabric_id')->filter()->unique()->all();
            $auxiliaryIds = collect($items)->where('item_type', 'AUXILIARY')->pluck('auxiliary_material_id')->filter()->unique()->all();
            $yarns     = $yarnIds ? Yarn::whereIn('id', $yarnIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
            $fabrics   = $fabricIds ? Fabric::whereIn('id', $fabricIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
            $auxiliaries = $auxiliaryIds ? AuxiliaryMaterial::whereIn('id', $auxiliaryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();

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

                if ($row['item_type'] === 'YARN') {
                    $rawMaterialAccount ??= $coa->account($company, 'raw_material_inventory');
                    $yarn = $yarns->get($row['yarn_id']) ?? throw new Exception("Yarn ID {$row['yarn_id']} tidak ditemukan.");
                    $result = MaterialCostHelper::receiveStock(
                        $yarn, 'YARN', $qty, $rate, $header['receipt_date'], $receiptNumber,
                        "Penerimaan MRN: {$receiptNumber} - {$row['item_name']}"
                    );
                    $accountCode = $rawMaterialAccount;
                } elseif ($row['item_type'] === 'FABRIC') {
                    $rawMaterialAccount ??= $coa->account($company, 'raw_material_inventory');
                    $fabric = $fabrics->get($row['fabric_id']) ?? throw new Exception("Fabric ID {$row['fabric_id']} tidak ditemukan.");
                    $result = MaterialCostHelper::receiveStock(
                        $fabric, 'FABRIC', $qty, $rate, $header['receipt_date'], $receiptNumber,
                        "Penerimaan MRN: {$receiptNumber} - {$row['item_name']}"
                    );
                    $accountCode = $rawMaterialAccount;
                } else {
                    $auxiliaryMaterialAccount ??= $coa->account($company, 'auxiliary_material_inventory');
                    $auxiliary = $auxiliaries->get($row['auxiliary_material_id']) ?? throw new Exception("Bahan penolong ID {$row['auxiliary_material_id']} tidak ditemukan.");
                    $result = MaterialCostHelper::receiveStock($auxiliary, 'AUXILIARY', $qty, $rate, $header['receipt_date'], $receiptNumber, "Penerimaan MRN: {$receiptNumber} - {$row['item_name']}");
                    $accountCode = $auxiliaryMaterialAccount;
                }

                $costByAccount[$accountCode] = ($costByAccount[$accountCode] ?? 0) + $amount;

                $detailRows[] = [
                    'po_detail_id' => $row['po_detail_id'] ?? null,
                    'item_type'    => $row['item_type'],
                    'yarn_id'      => $row['yarn_id'] ?? null,
                    'fabric_id'    => $row['fabric_id'] ?? null,
                    'auxiliary_material_id' => $row['auxiliary_material_id'] ?? null,
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
            $existing = JournalHeader::where('source_doc_no', $header['supplier_doc_no'] ?? $receiptNumber)
                ->where('transaction_type', 'Material Receipt (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor MRN '{$receiptNumber}' sudah memiliki jurnal. Kemungkinan race condition, ulangi proses.");
            }

            $journal = JournalHeader::create([
                'transaction_date' => $header['receipt_date'],
                'evidence_number'  => $receiptNumber,
                'source_doc_no'    => $header['supplier_doc_no'] ?? $receiptNumber,
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
                'account_code' => $accountsPayableAccount,
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
                    'account_code' => $inputVatAccount,
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

            foreach ($detailRows as $index => $row) {
                $detailRows[$index]['receipt_id'] = $receipt->id;
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

    /** Reverse a posted MRN while retaining source history. */
    public function void(int $receiptId, string $reason): bool
    {
        return app(MaterialReceiptVoidService::class)->void($receiptId, $reason);
    }
}

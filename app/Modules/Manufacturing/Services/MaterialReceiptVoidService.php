<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Modules\Manufacturing\Models\MaterialReceipt;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Support\JournalBalanceValidator;
use Illuminate\Support\Facades\DB;

class MaterialReceiptVoidService
{
    public function void(int $id, string $reason): bool
    {
        abort_unless(\App\Modules\Manufacturing\Support\MaterialReceiptVoidAuthorization::allows(auth()->user()), 403);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException(__('erp.audit_mrn_void_reason'));
        }
        return DB::transaction(function () use ($id, $reason) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $candidate = MaterialReceipt::findOrFail($id);
            // Same PO-before-material lock order as receiving.
            $po = $candidate->po_id ? MaterialPurchaseOrder::lockForUpdate()->findOrFail($candidate->po_id) : null;
            $receipt = MaterialReceipt::with('details')->lockForUpdate()->findOrFail($id);
            if ($receipt->status === 'VOIDED') return true;
            \App\Support\AccountingPeriodGuard::source([(string) $receipt->receipt_date, now()->toDateString()]);
            if ($receipt->status !== 'POSTED' || !$receipt->journal_id || $receipt->details->isEmpty()) {
                throw new \RuntimeException('MRN tidak memiliki snapshot POSTED yang dapat dibalik.');
            }
            $journal = JournalHeader::lockForUpdate()->findOrFail($receipt->journal_id);
            $lines = $journal->details()->lockForUpdate()->get();
            if ($lines->isEmpty() || !JournalBalanceValidator::isBalanced($lines->toArray())
                || round((float) $lines->where('position', 'DEBET')->sum('amount'), 2) !== round((float) $receipt->net_amount, 2)
                || $journal->transaction_type !== 'Material Receipt (MFG)') {
                throw new \RuntimeException('Jurnal asal MRN tidak konsisten.');
            }
            $groups = $receipt->details->groupBy(fn ($d) => $d->item_type.':'.($d->yarn_id ?? $d->fabric_id ?? $d->auxiliary_material_id));
            $updates = [];
            foreach ($groups->sortKeys() as $details) {
                $detail = $details->first();
                [$model, $itemId] = match ($detail->item_type) {
                    'YARN' => [Yarn::class, $detail->yarn_id],
                    'FABRIC' => [Fabric::class, $detail->fabric_id],
                    'AUXILIARY' => [AuxiliaryMaterial::class, $detail->auxiliary_material_id],
                    default => throw new \RuntimeException('Tipe material tidak valid.'),
                };
                $item = $model::lockForUpdate()->findOrFail($itemId);
                $ledgerQuery = MaterialLedger::where('item_type', $detail->item_type)->where('item_id', $itemId);
                $ledgers = (clone $ledgerQuery)->where('evidence_number', $receipt->receipt_number)->where('type', 'IN')->orderBy('id')->lockForUpdate()->get();
                $qty = round((float) $details->sum('qty'), 2);
                $value = round((float) $details->sum('amount'), 2);
                if ($ledgers->isEmpty() || round((float) $ledgers->sum('qty'), 2) !== $qty
                    || round((float) $ledgers->sum('total_cost'), 2) !== $value) {
                    throw new \RuntimeException('Ledger asal MRN tidak konsisten.');
                }
                $last = $ledgers->last();
                if ((clone $ledgerQuery)->where('id', '>', $last->id)->exists()) {
                    throw new \RuntimeException('Void ditolak: terdapat mutasi lanjutan pada material.');
                }
                if (round((float) $item->stock_quantity, 2) !== round((float) $last->running_qty, 2)
                    || abs((float) $item->average_cost - (float) $last->moving_average_cost) > 0.011) {
                    throw new \RuntimeException('Saldo material berbeda dari snapshot ledger.');
                }
                $previous = (clone $ledgerQuery)->where('id', '<', $ledgers->first()->id)->orderByDesc('id')->first();
                $stock = round((float) $last->running_qty - $qty, 2);
                $newValue = round((float) $last->running_value - $value, 2);
                if ($stock < 0 || $newValue < 0 || ($stock == 0 && abs($newValue) > 0.01)) {
                    throw new \RuntimeException('Saldo reversal material tidak valid.');
                }
                $mac = $previous ? (float) $previous->moving_average_cost : ($stock > 0 ? $newValue / $stock : 0);
                $updates[] = compact('item', 'detail', 'qty', 'value', 'stock', 'newValue', 'mac');
            }
            if ($po) {
                foreach ($receipt->details->groupBy('po_detail_id') as $detailId => $details) {
                    $row = DB::table('mfg_material_purchase_order_details')->where('po_id', $po->id)->where('id', $detailId)->lockForUpdate()->first();
                    $qty = round((float) $details->sum('qty'), 2);
                    if (!$row || (float) $row->qty_received < $qty) throw new \RuntimeException('Qty PO tidak konsisten.');
                    DB::table('mfg_material_purchase_order_details')->where('id', $detailId)->update(['qty_received' => round((float) $row->qty_received - $qty, 2), 'updated_at' => now()]);
                }
            }
            $reversal = JournalHeader::create(['transaction_date' => now()->toDateString(), 'source_doc_no' => 'VOID-'.$receipt->receipt_number,
                'transaction_type' => 'Material Receipt Reversal', 'description' => 'Void MRN '.$receipt->receipt_number]);
            foreach ($lines as $line) {
                JournalDetail::create(['journal_id' => $reversal->getKey(), 'account_code' => $line->account_code,
                    'helper_code' => $line->helper_code, 'position' => $line->position === 'DEBET' ? 'KREDIT' : 'DEBET', 'amount' => $line->amount]);
            }
            foreach ($updates as $u) {
                $u['item']->update(['stock_quantity' => $u['stock'], 'average_cost' => $u['mac']]);
                MaterialLedger::create(['transaction_date' => now()->toDateString(), 'evidence_number' => 'VOID-'.$receipt->receipt_number,
                    'item_type' => $u['detail']->item_type, 'item_id' => $u['item']->id, 'type' => 'OUT', 'qty' => $u['qty'],
                    'unit_cost' => $u['qty'] > 0 ? $u['value'] / $u['qty'] : 0, 'total_cost' => $u['value'],
                    'running_qty' => $u['stock'], 'running_value' => $u['newValue'], 'moving_average_cost' => $u['mac'],
                    'description' => 'Void MRN '.$receipt->receipt_number]);
            }
            $receipt->forceFill(['status' => 'VOIDED', 'void_reason' => $reason, 'voided_by' => auth()->id(),
                'voided_at' => now(), 'reversal_journal_id' => $reversal->getKey()])->save();
            \App\Models\SystemLog::record('VOID', 'Manufacturing Material Receipt', $receipt->receipt_number.'; reversal '.$reversal->getKey().'; reason '.$reason);
            return true;
        });
    }
}
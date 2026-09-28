<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\OperationalCompany;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Compatibility GRN: one receipt -> one new Bill -> the SAME legacy stock/journal event. */
class GrnReceivingService
{
    public function receive($poId, $date, array $items, $bill, $due, $key, Closure $postLegacy): int
    {
        abort_unless(config('platform.order_company_scope_enabled'), 409, 'GRN memerlukan ownership A2 aktif.');
        $companyId = app(OperationalCompany::class)->id();
        $bill = is_string($bill) ? trim($bill) : $bill;
        $key = is_string($key) ? strtolower(trim($key)) : $key;
        Validator::make(['date' => $date, 'due' => $due, 'bill' => $bill, 'key' => $key, 'items' => $items], [
            'date' => 'required|date_format:Y-m-d', 'due' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'bill' => 'required|string|max:100', 'key' => 'required|uuid',
            'items' => 'required|array|min:1', 'items.*' => 'required|integer|min:0',
        ])->validate();
        $normalized = [];
        foreach ($items as $id => $qty) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                throw new \RuntimeException('ID detail penerimaan tidak valid.');
            }
            if ((int) $qty > 0) { $normalized[(int) $id] = (int) $qty; }
        }
        if (! $normalized) { throw new \RuntimeException('Isi minimal satu kuantitas penerimaan positif.'); }
        ksort($normalized, SORT_NUMERIC);
        $hash = hash('sha256', json_encode([(int) $poId, $date, $due ?: null, $bill, $normalized], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($companyId, $poId, $date, $due, $bill, $key, $normalized, $hash, $postLegacy) {
            // Serializes new GRN events within the sole MGI deployment, including cross-PO bill/key collisions.
            Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            $po = PurchaseOrder::with('details')->lockForUpdate()->findOrFail($poId);
            $old = DB::table('purchase_receipts')->where('company_id', $companyId)->where('idempotency_key', $key)->first();
            if ($old) {
                if (! hash_equals((string) $old->payload_hash, $hash) || $old->status !== 'POSTED') {
                    throw new \RuntimeException('Request key sudah digunakan dengan isi berbeda atau status tidak valid.');
                }
                return (int) $old->id;
            }
            if ($po->receipt_mode === 'LEGACY' || (! $po->receipt_mode && $po->details->sum('qty_received') > 0)) {
                throw new \RuntimeException('PO mempunyai receiving legacy. Jangan memindahkan jalur pada dokumen berjalan.');
            }
            if (! in_array($po->status, ['APPROVED', 'PARTIAL'], true)) {
                throw new \RuntimeException('Status PO tidak dapat diterima.');
            }
            // V1 is deliberately limited to plain AP purchases; do not guess advance/tax settlement.
            $advance = DB::table('transaksi_payment_plan')->where('kategori_payment', 'PEMBELIAN PERSEDIAAN (UANG MUKA)')
                ->where(function ($q) use ($po) {
                    $q->where('no_transaksi', str_replace('PO-', '', $po->po_number));
                    if ($po->ref_po_number) { $q->orWhere('ref_po_number', $po->ref_po_number); }
                })->exists();
            if ($advance || $po->is_include_ppn || $po->tax_addition_id || $po->tax_deduction_id
                || (float) $po->tax_addition_amount != 0 || (float) $po->tax_deduction_amount != 0
                || $po->details->contains(fn ($d) => (float) $d->disc_amount != 0 || (float) $d->tax_amount != 0)) {
                throw new \RuntimeException('GRN V1 hanya pembelian hutang tanpa uang muka, pajak, atau diskon; alokasi lanjut belum didukung.');
            }
            if (DB::table('purchase_bills')->where('bill_number', $bill)->exists()
                || DB::table('journal_headers')->where('evidence_number', $bill)->exists()
                || DB::table('inventory_ledgers')->where('evidence_number', $bill)->exists()) {
                throw new \RuntimeException('Nomor Bill sudah digunakan. Gunakan nomor berbeda untuk penerimaan berbeda.');
            }
            $inventoryAccount = config('platform.grn_inventory_account');
            $payableAccount = config('platform.grn_payable_account');
            $accounts = DB::table('accounts')->whereIn('account_code', [$inventoryAccount, $payableAccount])->get()->keyBy('account_code');
            if (count($accounts) !== 2 || $accounts[$inventoryAccount]->normal_balance !== 'DEBET' || $accounts[$payableAccount]->normal_balance !== 'KREDIT') {
                throw new \RuntimeException('Mapping akun persediaan/hutang GRN belum valid. COA tidak dibuat otomatis.');
            }
            $products = Product::whereIn('sku', $po->details->pluck('item_code'))->orderBy('id')->lockForUpdate()->get()->keyBy('sku');
            $lines = []; $total = 0;
            foreach ($normalized as $detailId => $qty) {
                $detail = $po->details->firstWhere('id', $detailId);
                if (! $detail || $qty > $detail->qty - $detail->qty_received) {
                    throw new \RuntimeException('Detail bukan milik PO atau jumlah melebihi sisa pesanan.');
                }
                $product = $products->get($detail->item_code);
                if (! $product || ($detail->product_id && (int) $detail->product_id !== (int) $product->id) || (float) $detail->price <= 0) {
                    throw new \RuntimeException('SKU/product linkage atau harga PO tidak valid. Lengkapi master barang dahulu.');
                }
                $amount = round($qty * (float) $detail->price, 2);
                $total += $amount;
                $lines[] = ['purchase_order_detail_id' => $detailId, 'product_id' => $product->id,
                    'item_code' => $detail->item_code, 'description' => $detail->description,
                    'qty_received' => $qty, 'unit_cost' => $detail->price, 'amount' => $amount,
                    'created_at' => now(), 'updated_at' => now()];
            }
            $billId = DB::table('purchase_bills')->insertGetId(['bill_number' => $bill, 'bill_date' => $date,
                'due_date' => $due ?: null, 'vendor_name' => $po->contact_name, 'payment_status' => 'UNPAID',
                'sub_total' => $total, 'tax_amount' => 0, 'grand_total' => $total,
                'credit_account' => $payableAccount, 'notes' => 'GRN Ref PO: '.$po->po_number,
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_bill_details')->insert(['purchase_bill_id' => $billId, 'account_code' => $inventoryAccount,
                'description' => 'Penerimaan PO '.$po->po_number, 'amount' => $total, 'created_at' => now(), 'updated_at' => now()]);
            $receiptId = DB::table('purchase_receipts')->insertGetId(['company_id' => $companyId,
                'purchase_order_id' => $po->id, 'party_id' => $po->party_id,
                'receipt_number' => 'GRN-'.Str::uuid(), 'receipt_date' => $date, 'status' => 'DRAFT',
                'purchase_bill_id' => $billId, 'idempotency_key' => $key, 'payload_hash' => $hash,
                'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as &$line) { $line['purchase_receipt_id'] = $receiptId; }
            unset($line);
            DB::table('purchase_receipt_details')->insert($lines);
            $po->receipt_mode = 'GRN_V1';
            $po->save();
            // Existing code alone changes qty, moving average cost, ledger, and journal (once).
            $grnAccounts = ['inventory' => $inventoryAccount, 'payable' => $payableAccount];
            $postLegacy($poId, $date, $normalized, $bill, $due, $grnAccounts);
            $journalId = DB::table('purchase_bills')->where('id', $billId)->value('journal_id');
            if (! $journalId) { throw new \RuntimeException('Posting tidak menghasilkan linkage jurnal Bill.'); }
            DB::table('purchase_receipts')->where('id', $receiptId)->update(['status' => 'POSTED', 'journal_id' => $journalId, 'updated_at' => now()]);
            return $receiptId;
        });
    }
}

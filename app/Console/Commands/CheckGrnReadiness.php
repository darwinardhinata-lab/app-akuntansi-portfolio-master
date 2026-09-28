<?php

namespace App\Console\Commands;

use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckGrnReadiness extends Command
{
    protected $signature = 'platform:check-grn';
    protected $description = 'Read-only GRN schema, ownership, linkage and quantity reconciliation';

    public function handle(): int
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                || ! str_starts_with((string) DB::selectOne('SELECT DATABASE() AS db')->db, 'mgi_fresh_')) {
                throw new \RuntimeException('Gunakan database MySQL hasil fresh start MGI.');
            }
            if (! config('platform.order_company_scope_enabled')) { throw new \RuntimeException('Ownership A2 harus aktif.'); }
            $company = app(OperationalCompany::class)->company();
            if ($this->call('platform:check-order-ownership') !== self::SUCCESS) { return self::FAILURE; }
            foreach (['purchase_orders' => ['receipt_mode'], 'purchase_receipts' => ['idempotency_key', 'payload_hash', 'purchase_bill_id']] as $table => $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) { throw new \RuntimeException('Migration A3 belum lengkap: '.$table.'.'.$column); }
                }
            }
            $inventoryAccount = config('platform.grn_inventory_account');
            $payableAccount = config('platform.grn_payable_account');
            if (! is_string($inventoryAccount) || ! is_string($payableAccount) || $inventoryAccount === $payableAccount) {
                throw new \RuntimeException('Mapping COA GRN belum valid.');
            }
            $accounts = DB::table('accounts')->whereIn('account_code', [$inventoryAccount, $payableAccount])->get()->keyBy('account_code');
            if (count($accounts) !== 2
                || $accounts[$inventoryAccount]->normal_balance !== 'DEBET'
                || $accounts[$payableAccount]->normal_balance !== 'KREDIT') {
                throw new \RuntimeException('Mapping COA GRN belum valid.');
            }
            $count = 0;
            foreach (DB::table('purchase_receipts')->orderBy('id')->cursor() as $r) {
                $po = DB::table('purchase_orders')->where('id', $r->purchase_order_id)->first();
                $bill = DB::table('purchase_bills')->where('id', $r->purchase_bill_id)->first();
                $journal = DB::table('journal_headers')->where('journal_id', $r->journal_id)->first();
                if ((int) $r->company_id !== (int) $company->id || $r->status !== 'POSTED' || ! $r->idempotency_key || ! $r->payload_hash
                    || ! $po || $po->receipt_mode !== 'GRN_V1' || ! $bill || ! $journal
                    || $bill->journal_id !== $r->journal_id || $journal->evidence_number !== $bill->bill_number) {
                    throw new \RuntimeException('Linkage GRN tidak valid: ID '.$r->id);
                }
                $lines = DB::table('journal_details')->where('journal_id', $r->journal_id)
                    ->orderBy('position')->get(['account_code', 'position', 'amount']);
                $billDetailAccount = DB::table('purchase_bill_details')
                    ->where('purchase_bill_id', $r->purchase_bill_id)->value('account_code');
                $amount = DB::table('purchase_receipt_details')->where('purchase_receipt_id', $r->id)->sum('amount');
                $debit = (float) $lines->where('position', 'DEBET')->sum('amount');
                $credit = (float) $lines->where('position', 'KREDIT')->sum('amount');
                if ($lines->count() !== 2
                    || $lines[0]->account_code !== $inventoryAccount || $lines[0]->position !== 'DEBET'
                    || $lines[1]->account_code !== $payableAccount || $lines[1]->position !== 'KREDIT'
                    || $bill->credit_account !== $payableAccount || $billDetailAccount !== $inventoryAccount
                    || round((float) $amount, 2) !== round((float) $bill->grand_total, 2)
                    || round((float) $amount, 2) !== round($debit, 2) || round($debit, 2) !== round($credit, 2)) {
                    throw new \RuntimeException('Nilai/akun GRN-Bill-jurnal tidak cocok: ID '.$r->id);
                }
                $count++;
            }
            foreach (DB::table('purchase_order_details as d')->join('purchase_orders as p', 'p.id', '=', 'd.purchase_order_id')
                ->where('p.receipt_mode', 'GRN_V1')->select('d.*')->orderBy('d.id')->cursor() as $d) {
                $received = DB::table('purchase_receipt_details as rd')->join('purchase_receipts as r', 'r.id', '=', 'rd.purchase_receipt_id')
                    ->where('rd.purchase_order_detail_id', $d->id)->where('r.status', 'POSTED')->sum('rd.qty_received');
                if ((int) $received !== (int) $d->qty_received || $received > $d->qty) {
                    throw new \RuntimeException('Qty GRN/PO tidak cocok: detail '.$d->id);
                }
            }
            $this->info('GRN_READINESS=PASSED; flag='.(config('platform.grn_enabled') ? 'ON' : 'OFF').'; posted='.$count);
            $this->line('V1: single MGI, plain AP only, one new Bill per receipt; no reversal or tax/advance allocation.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof \Illuminate\Database\QueryException ? 'Query readiness gagal; periksa schema.' : $e->getMessage());
            return self::FAILURE;
        }
    }
}

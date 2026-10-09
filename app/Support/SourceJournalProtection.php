<?php

namespace App\Support;

use App\Models\JournalHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SourceJournalProtection
{
    public static function check(JournalHeader $header): void
    {
        $protected = $header->is_opening_balance
            || str_starts_with((string) $header->source_doc_no, 'SA-')
            || str_starts_with((string) $header->evidence_number, 'SA-')
            || ! in_array(strtoupper((string) $header->journal_type), ['', 'MANUAL', 'IMPORT'], true);
        $protected = $protected || (! in_array(strtoupper((string) $header->journal_type), ['MANUAL', 'IMPORT'], true)
            && trim((string) $header->transaction_type) !== '');

        foreach (['payment_id', 'invoice_id', 'bill_id', 'sales_ret_id', 'purch_ret_id', 'item_adj_id'] as $field) {
            $protected = $protected || $header->$field !== null;
        }

        foreach (['sales_invoices', 'purchase_bills', 'sales_returns', 'purchase_returns', 'purchase_receipts', 'mfg_work_orders', 'mfg_material_receipts'] as $table) {
            if (Schema::hasColumn($table, 'journal_id')) {
                $protected = $protected || DB::table($table)->where('journal_id', $header->getKey())->exists();
            }
        }
        $protected = $protected || str_starts_with($header->getKey(), 'JRN-PP-');
        if (Schema::hasTable('invoice_payment_allocations')) {
            $protected = $protected || DB::table('invoice_payment_allocations')->where('journal_id', $header->getKey())->exists();
        }

        if ($protected) {
            throw new \RuntimeException(__('erp.audit_source_journal_protected'));
        }
    }
}
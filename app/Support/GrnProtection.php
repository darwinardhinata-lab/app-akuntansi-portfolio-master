<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Durable protection also applies when the new-document feature flag is switched off. */
class GrnProtection
{
    public static function evidence(?string $evidenceNumber, ?string $sourceDocumentNumber = null): void
    {
        // GRN protects against the physical Bill/transaction number. The internal
        // evidence number is intentionally different (e.g. GRN-YYYYMMDD-0001).
        $number = $sourceDocumentNumber ?: $evidenceNumber;

        if ($number && Schema::hasColumn('purchase_receipts', 'purchase_bill_id')
            && DB::table('purchase_receipts as r')->join('purchase_bills as b', 'b.id', '=', 'r.purchase_bill_id')
                ->where('r.status', 'POSTED')->where('b.bill_number', $number)->exists()) {
            throw new \RuntimeException('Nomor bukti sudah diposting melalui GRN.');
        }
    }

    public static function journals(array $ids): void
    {
        if ($ids && Schema::hasTable('purchase_receipts')
            && DB::table('purchase_receipts')->whereIn('journal_id', $ids)->where('status', 'POSTED')->exists()) {
            throw new \RuntimeException('Jurnal GRN posted tidak dapat ditimpa/dihapus. Reversal GRN belum tersedia.');
        }
    }

    public static function bill(int $id): void
    {
        if (Schema::hasColumn('purchase_receipts', 'purchase_bill_id')
            && DB::table('purchase_receipts')->where('purchase_bill_id', $id)->exists()) {
            throw new \RuntimeException('Bill terhubung GRN; pembatalan legacy tidak diizinkan.');
        }
    }

    public static function po($po): void
    {
        if ($po && $po->receipt_mode === 'GRN_V1') {
            throw new \RuntimeException('PO sudah menggunakan GRN; detail/header tidak boleh diganti melalui import atau void legacy.');
        }
    }
}

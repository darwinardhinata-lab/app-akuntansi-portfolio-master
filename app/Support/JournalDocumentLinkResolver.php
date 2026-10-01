<?php

namespace App\Support;

use App\Models\JournalHeader;
use App\Models\PaymentPlan;
use App\Models\PurchaseBill;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Modules\Manufacturing\Models\MaterialReceipt;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class JournalDocumentLinkResolver
{
    /**
     * Attach a safe, context-aware source link to each journal header.
     * IMPORT and manual journals deliberately always resolve to the manual journal edit page.
     *
     * @param Collection<int, JournalHeader> $headers
     */
    public static function attach(Collection $headers): void
    {
        $ids = $headers->pluck('journal_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $byJournal = [];
        self::addModelLinksWhenSupported($byJournal, SalesInvoice::class, $ids, 'invoice.show', 'Invoice Penjualan');
        self::addModelLinksWhenSupported($byJournal, PurchaseBill::class, $ids, 'purchase-bills.show', 'Tagihan Pembelian');
        self::addModelLinksWhenSupported($byJournal, SalesReturn::class, $ids, 'sales-returns.show', 'Retur Penjualan');
        self::addModelLinksWhenSupported($byJournal, PurchaseReturn::class, $ids, 'purchase-returns.show', 'Retur Pembelian');
        self::addModelLinksWhenSupported($byJournal, PurchaseReceipt::class, $ids, 'grn.show', 'Penerimaan Barang');
        self::addModelLinksWhenSupported($byJournal, WorkOrder::class, $ids, 'mfg.work-orders.show', 'SPK Produksi');
        self::addModelLinksWhenSupported($byJournal, MaterialReceipt::class, $ids, 'mfg.material-receipts.show', 'MRN Bahan');

        $paymentLinks = PaymentPlan::get(['id_payment', 'no_transaksi'])
            ->mapWithKeys(fn (PaymentPlan $payment) => [
                JournalHeader::idForPaymentPlan($payment->no_transaksi) => self::link('payment.edit', $payment->id_payment, 'Payment Plan'),
            ]);

        foreach ($headers as $header) {
            $manualLink = self::journalLink($header);
            $type = strtoupper((string) $header->journal_type);

            if (in_array($type, ['IMPORT', 'MANUAL'], true)) {
                $header->setAttribute('document_link', $manualLink);
                continue;
            }

            $header->setAttribute('document_link', $byJournal[$header->journal_id]
                ?? $paymentLinks->get($header->journal_id)
                ?? self::legacyLink($header)
                ?? $manualLink);
        }
    }

    private static function addByJournalId(array &$links, Collection $documents, string $route, string $label): void
    {
        foreach ($documents as $document) {
            $links[$document->journal_id] = self::link($route, $document->getKey(), $label);
        }
    }

    /**
     * Some production databases predate journal_id columns on operational tables.
     * Never issue a journal_id query until the physical schema supports it.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    private static function addModelLinksWhenSupported(array &$links, string $model, Collection $ids, string $route, string $label): void
    {
        $instance = new $model;
        if (! Schema::hasColumn($instance->getTable(), 'journal_id')) {
            return;
        }

        self::addByJournalId($links, $model::whereIn('journal_id', $ids)->get(), $route, $label);
    }

    private static function legacyLink(JournalHeader $header): ?array
    {
        $evidence = trim((string) $header->evidence_number);
        if ($evidence === '') {
            return null;
        }

        $prefix = strtoupper(explode('-', $evidence)[0]);
        $match = match ($prefix) {
            'INV' => [SalesInvoice::class, 'invoice_number', 'invoice.show', 'Invoice Penjualan'],
            'BIL' => [PurchaseBill::class, 'bill_number', 'purchase-bills.show', 'Tagihan Pembelian'],
            'SR' => [SalesReturn::class, 'return_number', 'sales-returns.show', 'Retur Penjualan'],
            'PR' => [PurchaseReturn::class, 'return_number', 'purchase-returns.show', 'Retur Pembelian'],
            'MRN' => [MaterialReceipt::class, 'receipt_number', 'mfg.material-receipts.show', 'MRN Bahan'],
            'MFG', 'SPK' => [WorkOrder::class, 'spk_number', 'mfg.work-orders.show', 'SPK Produksi'],
            default => null,
        };

        if ($match === null) {
            return null;
        }

        [$model, $column, $route, $label] = $match;
        $document = $model::where($column, $evidence)->first();

        return $document ? self::link($route, $document->getKey(), $label) : null;
    }

    private static function journalLink(JournalHeader $header): array
    {
        return self::link('jurnal.edit', $header->journal_id, 'Edit Jurnal');
    }

    private static function link(string $route, int|string $parameter, string $label): array
    {
        return ['url' => route($route, $parameter), 'label' => $label, 'is_journal' => $route === 'jurnal.edit'];
    }
}
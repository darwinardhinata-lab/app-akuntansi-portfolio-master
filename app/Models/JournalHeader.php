<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalHeader extends Model
{
    protected $table = 'journal_headers';

    protected $primaryKey = 'journal_id';

    public $incrementing = false;

    // Pastikan Laravel tahu Primary Key ini adalah teks/string, bukan angka auto-increment
    protected $keyType = 'string';

    protected $fillable = [
        // --- Kolom Bawaan Sistem Anda ---
        'journal_id',
        'transaction_date',
        'evidence_number',
        'jj_id',
        // --- Kolom Tambahan (sinkronisasi data eksternal) ---
        'journal_no',
        'source_doc_no',
        'payment_id',
        'invoice_id',
        'bill_id',
        'sales_ret_id',
        'purch_ret_id',
        'item_adj_id',
        'is_opening_balance',
        'debit',
        'credit',
        'notes',
        'journal_type',
        'transaction_type',
        'tags'
    ];

    protected static function booted(): void
    {
        static::updating(fn (JournalHeader $header) => \App\Support\GrnProtection::journals([$header->getKey()]));
        static::deleting(fn (JournalHeader $header) => \App\Support\GrnProtection::journals([$header->getKey()]));
        static::creating(function (JournalHeader $header) {
            $header->assignInternalEvidenceNumber();
            \App\Support\GrnProtection::evidence($header->evidence_number, $header->source_doc_no);
            // Jika jurnal dibuat manual (ID kosong), pakai generator JRN- Anda
            if (empty($header->journal_id)) {
                $header->journal_id = static::generateNextId();
            }
            // FIX 1364: Injeksi dummy ID (Timestamp) agar database MySQL tidak menolak transaksi manual ERP
            if (!isset($header->jj_id)) {
                $header->jj_id = time() + rand(100, 999);
            }
        });
    }

    /**
     * No. Bukti is an internal, immutable accounting key: CODE-YYYYMMDD-0000.
     * The physical/original transaction document number belongs in source_doc_no.
     */
    private function assignInternalEvidenceNumber(): void
    {
        $type = strtoupper((string) $this->journal_type);
        $existingEvidence = trim((string) $this->evidence_number);

        // Bulk imports explicitly set their own generated evidence number.
        if ($type === 'IMPORT' && $existingEvidence !== '') {
            return;
        }

        // Legacy callers supplied the physical document number in evidence_number.
        // Preserve it as No. Transaksi before assigning the internal No. Bukti.
        if (empty($this->source_doc_no) && $existingEvidence !== '') {
            $this->source_doc_no = $existingEvidence;
        }

        $this->evidence_number = static::generateEvidenceNumber(
            static::evidenceCodeFor($type, (string) $this->transaction_type),
            $this->transaction_date
        );
    }

    public static function generateEvidenceNumber(string $code, mixed $transactionDate): string
    {
        $date = \Illuminate\Support\Carbon::parse($transactionDate ?: now())->format('Ymd');
        $prefix = strtoupper($code).'-'.$date.'-';

        return \Illuminate\Support\Facades\DB::transaction(function () use ($prefix) {
            $last = static::where('evidence_number', 'like', $prefix.'%')
                ->orderByDesc('evidence_number')
                ->lockForUpdate()
                ->value('evidence_number');

            $sequence = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    private static function evidenceCodeFor(string $journalType, string $transactionType): string
    {
        if ($journalType === 'MANUAL') return 'GJ';
        if ($journalType === 'IMPORT') return 'IMP';

        $type = strtoupper($transactionType);

        return match (true) {
            str_contains($type, 'INVOICE'), str_contains($type, 'FAKTUR') => 'INV',
            str_contains($type, 'PURCHASE BILL'), str_contains($type, 'TAGIHAN') => 'BIL',
            str_contains($type, 'PURCHASE RETURN') => 'PR',
            str_contains($type, 'SALES RETURN') => 'SR',
            str_contains($type, 'MATERIAL RECEIPT') => 'MRN',
            str_contains($type, 'WORK ORDER') => 'SPK',
            str_contains($type, 'INBOUND') => 'GRN',
            str_contains($type, 'OUTBOUND') => 'OUT',
            str_contains($type, 'PAYMENT') => 'PP',
            default => 'GJ',
        };
    }

    /**
     * Generate sequential journal ID: JRN-YYYYMMDD-0001
     */
    public static function generateNextId(): string
    {
        // FIX: Gunakan Transaction & lockForUpdate agar nomor urut tidak tabrakan saat ada request paralel
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $prefix = 'JRN-' . now()->format('Ymd') . '-';

            $last = static::where('journal_id', 'like', $prefix . '%')
                ->orderByDesc('journal_id')
                ->lockForUpdate()
                ->value('journal_id');

            $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Deterministic ID for payment plan posting (unique per no_transaksi).
     */
    public static function idForPaymentPlan(string $noTransaksi): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($noTransaksi));
        $id   = 'JRN-PP-' . substr($slug, 0, 40);

        return substr($id, 0, 50);
    }

    /**
     * Backward-compatibility accessor:
     * Kolom di database adalah 'notes', bukan 'description'.
     * Accessor ini memastikan $header->description tetap berfungsi.
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->attributes['notes'] ?? null;
    }

    public function details()
    {
        return $this->hasMany(JournalDetail::class, 'journal_id', 'journal_id');
    }

    /**
     * Scope: Exclude opening-balance journal entries (SA- evidence_number
     * or is_opening_balance = 1) from P&L calculations.
     *
     * Opening-balance entries are internal setup/corrective entries that
     * must NOT be counted as current-period revenue/expense — they cause
     * discrepancies vs. the external source system which never sees these entries.
     *
     * NOTE: This scope is ONLY for P&L queries (account prefix 4–9).
     * For Balance Sheet opening rows (prefix 1–3), opening-balance
     * entries MUST still be included — use the raw query without this scope.
     */
    public function scopeExcludeOpeningBalance($query)
    {
        return $query->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                     ->where(function ($q) {
                         $q->whereNull('journal_headers.is_opening_balance')
                           ->orWhere('journal_headers.is_opening_balance', 0);
                     });
    }
}

<?php

namespace App\Support;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Closure;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Persists a balanced journal header and its details.
 *
 * This service is deliberately limited to persistence and balance validation.
 * Callers remain responsible for constructing the accounting entries.
 */
class PostingService
{
    /**
     * @param  array<string, mixed>  $headerAttributes
     * @param  array<int, array<string, mixed>>  $lines
     * @param  string|Closure(float): string|null  $unbalancedMessage
     * @throws Exception
     */
    // FIX: multicurrency — argumen FX opsional; jalur null tetap identik dengan sebelumnya.
    public static function post(array $headerAttributes, array $lines, string|Closure|null $unbalancedMessage = null, ?array $fx = null, ?array $intent = null): JournalHeader
    {
        if ($intent !== null) {
            if (!config('platform.posting_intents_enabled', false)) {
                throw new \RuntimeException('Accounting posting intents are disabled.');
            }
            foreach (['idempotency_key' => 150, 'source_type' => 80, 'source_id' => 150] as $field => $limit) {
                if (!is_string($intent[$field] ?? null) || trim($intent[$field]) === '' || strlen($intent[$field]) > $limit) {
                    throw new \InvalidArgumentException('Explicit posting intent required: '.$field);
                }
            }
            $hash = hash('sha256', json_encode(self::canonicalPayload([$headerAttributes, $lines, $fx, $intent]), JSON_THROW_ON_ERROR));
            return DB::transaction(function () use ($headerAttributes, $lines, $unbalancedMessage, $fx, $intent, $hash) {
                // Unique key arbitration followed by a current locking read also handles retries.
                DB::table('accounting_posting_intents')->insertOrIgnore([
                    'idempotency_key' => $intent['idempotency_key'], 'payload_hash' => $hash,
                    'source_type' => $intent['source_type'], 'source_id' => $intent['source_id'],
                    'actor_id' => $intent['actor_id'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $stored = DB::table('accounting_posting_intents')->where('idempotency_key', $intent['idempotency_key'])->lockForUpdate()->first();
                if (!$stored || !hash_equals($stored->payload_hash, $hash)) {
                    throw new \RuntimeException('Idempotency key payload conflict.');
                }
                if ($stored->journal_id !== null) {
                    return JournalHeader::findOrFail($stored->journal_id);
                }
                $header = self::post($headerAttributes, $lines, $unbalancedMessage, $fx);
                DB::table('accounting_posting_intents')->where('idempotency_key', $intent['idempotency_key'])->update([
                    'journal_id' => $header->getKey(), 'sealed_at' => now(), 'updated_at' => now(),
                ]);
                return $header;
            });
        }
        // Validate FX snapshots before writes; both FX and base paths are atomic.
        if ($fx !== null) {
            if (! config('platform.multicurrency_enabled')) {
                throw new \RuntimeException('Multicurrency belum diaktifkan; posting valas dibatalkan.');
            }
            return \Illuminate\Support\Facades\DB::transaction(function () use ($headerAttributes, $lines, $unbalancedMessage, $fx) {
                // FIX: multicurrency — status master tidak boleh berubah di antara validasi dan insert.
                if (is_string($fx['currency'] ?? null)) {
                    \App\Modules\Currency\Services\FxPostingService::requireForeignCurrency($fx['currency'], $fx['company_id'] ?? null);
                }
                $lines = \App\Modules\Currency\Services\FxPostingService::validateLines($lines, $fx);
                $headerAttributes['currency_code'] = $fx['currency'];
                $headerAttributes['exchange_rate'] = \App\Modules\Currency\Support\Money::round($fx['rate'], 8);

                return self::post($headerAttributes, $lines, $unbalancedMessage);
            });
        }
        if (! JournalBalanceValidator::isBalanced($lines)) {
            $difference = JournalBalanceValidator::getDifference($lines);
            $message = $unbalancedMessage instanceof Closure
                ? $unbalancedMessage($difference)
                : ($unbalancedMessage ?? ('Jurnal tidak balance. Selisih: Rp ' . number_format($difference, 2, ',', '.')));

            throw new Exception($message);
        }

        // FIX: Kolom 'description' tidak ada di journal_headers dan tidak masuk $fillable, sehingga
        // narasi jurnal dari semua pemanggil terbuang diam-diam. Petakan ke 'notes' (kolom yang
        // dibaca JournalController/LedgerController sebagai keterangan) bila 'notes' belum diisi.
        if (array_key_exists('description', $headerAttributes)) {
            if (! isset($headerAttributes['notes']) || $headerAttributes['notes'] === '') {
                $headerAttributes['notes'] = $headerAttributes['description'];
            }
            unset($headerAttributes['description']);
        }

        return DB::transaction(function () use ($headerAttributes, $lines) {
            $header = JournalHeader::create($headerAttributes);
            $rows = [];

            foreach ($lines as $line) {
                $line['journal_id'] = $header->getKey();
                $rows[] = $line;
            }

            if (! empty($rows)) {
                JournalDetail::insert($rows);
            }

            return $header;
        });
    }

    /** Reverse manual GL only; source documents/assets require their domain reversal. */
    public static function reverse(string $journalId, string $date, string $reason, \App\Models\User $actor): JournalHeader
    {
        abort_unless(config('platform.posting_intents_enabled', false), 409, 'Accounting posting intents are disabled.');
        abort_unless($actor->exists && $actor->role === 'FINANCE'
            && in_array((string) $actor->getKey(), array_map('strval', config('platform.journal_reversal_user_ids', [])), true), 403);
        if (trim($reason) === '' || strlen($reason) > 255) throw new \InvalidArgumentException('Reversal reason required (maximum 255 bytes).');
        return DB::transaction(function () use ($journalId, $date, $reason, $actor) {
            $original = JournalHeader::whereKey($journalId)->lockForUpdate()->firstOrFail();
            $intent = DB::table('accounting_posting_intents')->where('journal_id', $journalId)->whereNotNull('sealed_at')->lockForUpdate()->first();
            if (!$intent || $intent->reversal_of !== null || $original->journal_type !== 'MANUAL') {
                throw new \RuntimeException('Only sealed manual originals support generic reversal.');
            }
            SourceJournalProtection::check($original);
            $details = $original->details()->orderBy('id')->get();
            if (DB::table('assets')->whereIn('journal_detail_id', $details->pluck('id'))->exists()) {
                throw new \RuntimeException('Asset journal requires domain reversal.');
            }
            // A closed original can be reversed into an explicitly open posting period.
            AccountingPeriodGuard::lock();
            AccountingPeriodGuard::dates([$date]);
            $lines = $details->map(function ($detail) {
                $line = $detail->getAttributes();
                unset($line['id'], $line['journal_id'], $line['created_at'], $line['updated_at']);
                $line['position'] = $line['position'] === 'DEBET' ? 'KREDIT' : 'DEBET';
                return $line;
            })->all();
            $reversal = self::post([
                'transaction_date' => $date, 'journal_type' => 'REVERSAL',
                'source_doc_no' => $journalId, 'notes' => $reason,
                'currency_code' => $original->currency_code, 'exchange_rate' => $original->exchange_rate,
            ], $lines, null, null, [
                'idempotency_key' => 'reversal:'.$journalId,
                'source_type' => 'JOURNAL_REVERSAL', 'source_id' => $journalId,
                'actor_id' => (string) $actor->getKey(),
            ]);
            DB::table('accounting_posting_intents')->where('journal_id', $reversal->getKey())->update(['reversal_of' => $journalId]);
            return $reversal;
        });
    }

    private static function canonicalPayload(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        // Persistence timestamps are generated per retry, not accounting input.
        unset($value['created_at'], $value['updated_at']);
        if (!array_is_list($value)) ksort($value);
        return array_map(fn ($item) => self::canonicalPayload($item), $value);
    }
}
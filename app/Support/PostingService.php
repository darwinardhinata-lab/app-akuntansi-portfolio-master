<?php

namespace App\Support;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Closure;
use Exception;

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
    public static function post(array $headerAttributes, array $lines, string|Closure|null $unbalancedMessage = null, ?array $fx = null): JournalHeader
    {
        // FIX: multicurrency — validasi sebelum write; transaksi atomik hanya untuk jalur FX baru.
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
    }
}
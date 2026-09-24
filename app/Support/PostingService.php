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
    public static function post(array $headerAttributes, array $lines, string|Closure|null $unbalancedMessage = null): JournalHeader
    {
        if (! JournalBalanceValidator::isBalanced($lines)) {
            $difference = JournalBalanceValidator::getDifference($lines);
            $message = $unbalancedMessage instanceof Closure
                ? $unbalancedMessage($difference)
                : ($unbalancedMessage ?? ('Jurnal tidak balance. Selisih: Rp ' . number_format($difference, 2, ',', '.')));

            throw new Exception($message);
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
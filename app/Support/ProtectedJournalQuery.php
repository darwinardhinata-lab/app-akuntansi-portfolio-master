<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit replacement for raw journal table mutations; not a SQL firewall. */
class ProtectedJournalQuery extends Builder
{
    public static function table(string $table): self
    {
        if (! in_array($table, ['journal_headers', 'journal_details'], true)) {
            throw new RuntimeException('ProtectedJournalQuery only supports journal tables.');
        }
        $connection = DB::connection();

        return (new self($connection, $connection->getQueryGrammar(), $connection->getPostProcessor()))->from($table);
    }

    private function checkRows(array $values): void
    {
        $rows = isset($values[0]) && is_array($values[0]) ? $values : ($values ? [$values] : []);
        $ids = [];
        foreach ($rows as $row) {
            if (! isset($row['journal_id']) || ! is_scalar($row['journal_id'])) {
                throw new RuntimeException('Journal ID must be explicit for protected writes.');
            }
            $ids[] = (string) $row['journal_id'];
        }
        MaklunJournalProtection::check(array_unique($ids));
    }

    private function periodWrite(callable $operation, array $values = [], bool $insert = false)
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('accounting_posting_intents')) {
            return $this->connection->transaction(function () use ($operation, $values, $insert) {
                $ids = $insert ? [] : (clone $this)->lockForUpdate()->pluck('journal_id')->all();
                $rows = isset($values[0]) && is_array($values[0]) ? $values : ($values ? [$values] : []);
                foreach ($rows as $row) {
                    if (isset($row['journal_id'])) $ids[] = $row['journal_id'];
                }
                if ($ids && $this->connection->table('accounting_posting_intents')->whereIn('journal_id', $ids)
                    ->whereNotNull('sealed_at')->lockForUpdate()->first()) {
                    throw new RuntimeException('Posted journal is immutable; use linked reversal.');
                }
                return $this->guardPeriodWrite($operation, $values, $insert);
            });
        }
        return $this->guardPeriodWrite($operation, $values, $insert);
    }

    private function guardPeriodWrite(callable $operation, array $values, bool $insert)
    {
        if (!AccountingPeriodGuard::enabled()) return $operation();
        return $this->connection->transaction(function () use ($operation, $values, $insert) {
            AccountingPeriodGuard::lock();
            if (!$insert) AccountingPeriodGuard::journals((clone $this)->lockForUpdate()->pluck('journal_id')->all());
            $rows = $insert && isset($values[0]) && is_array($values[0]) ? $values : ($values ? [$values] : []);
            foreach ($rows as $row) {
                if ($this->from === 'journal_headers' && ($insert || array_key_exists('transaction_date', $row))) {
                    AccountingPeriodGuard::dates([$row['transaction_date'] ?? null]);
                }
                if ($this->from === 'journal_details' && ($insert || array_key_exists('journal_id', $row))) {
                    if (!isset($row['journal_id']) || !is_string($row['journal_id']) || trim($row['journal_id']) === '') {
                        throw new RuntimeException('Explicit journal parent is required for period protection.');
                    }
                    AccountingPeriodGuard::journals([$row['journal_id']]);
                }
            }
            return $operation();
        });
    }

    private function checkTargets(): void
    {
        if ($this->joins || $this->groups || $this->unions) {
            throw new RuntimeException('Joined/grouped journal mutations are not supported.');
        }
        MaklunJournalProtection::check((clone $this)->pluck('journal_id')->all());
    }

    public function insert(array $values)
    {
        $this->checkRows($values);

        return $this->periodWrite(fn () => $this->currencyWrite(fn () => parent::insert($values), $values, true), $values, true);
    }

    public function insertOrIgnore(array $values)
    {
        $this->checkRows($values);

        return $this->periodWrite(fn () => $this->currencyWrite(fn () => parent::insertOrIgnore($values), $values, true), $values, true);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->checkRows($values);

        return $this->periodWrite(fn () => $this->currencyWrite(fn () => parent::insertGetId($values, $sequence), $values, true), $values, true);
    }

    public function update(array $values)
    {
        $this->checkTargets();
        if (array_key_exists('maklun_sealed', $values)) {
            throw new RuntimeException('Seal flag cannot be changed through ordinary journal writes.');
        }
        if (array_key_exists('journal_id', $values)) {
            $this->checkRows([['journal_id' => $values['journal_id']]]);
        }

        return $this->periodWrite(fn () => $this->currencyWrite(
            fn ($ids = null) => $ids === null ? parent::update($values) : $this->updateValidatedIds($ids, $values),
            $values,
            false
        ), $values);
    }

    private function updateValidatedIds(array $ids, array $values)
    {
        $query = clone $this;
        $query->whereIn('id', $ids);

        return $query->updateWithoutCurrencyGuard($values);
    }

    private function updateWithoutCurrencyGuard(array $values)
    {
        return parent::update($values);
    }

    private function currencyWrite(callable $operation, array $values, bool $insert)
    {
        if ($this->from !== 'journal_details' || ! config('platform.multicurrency_enabled', false)) {
            return $operation();
        }
        if (! $insert && ! array_intersect(array_keys($values), ['account_code', 'currency_code', 'amount_txn', 'exchange_rate'])) {
            return $operation();
        }

        return $this->connection->transaction(function () use ($operation, $values, $insert) {
            if ($insert) {
                $rows = isset($values[0]) && is_array($values[0]) ? $values : ($values ? [$values] : []);
                \App\Modules\Currency\Support\AccountCurrencyGuard::validate($rows, $this->connection);

                return $operation();
            }
            foreach (['account_code', 'currency_code', 'amount_txn', 'exchange_rate'] as $column) {
                if (isset($values[$column]) && ! is_scalar($values[$column])) {
                    throw new RuntimeException('Perubahan mata uang jurnal memerlukan nilai eksplisit: '.$column.'.');
                }
            }
            $targets = (clone $this)->lockForUpdate()->get(['id', 'account_code', 'currency_code', 'amount_txn', 'exchange_rate']);
            $rows = [];
            foreach ($targets as $target) {
                $rows[] = array_merge((array) $target, $values);
            }
            \App\Modules\Currency\Support\AccountCurrencyGuard::validate($rows, $this->connection);

            return $operation($targets->pluck('id')->all());
        });
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            throw new RuntimeException('Use explicit journal filter rather than delete(id).');
        }
        $this->checkTargets();

        return $this->periodWrite(fn () => parent::delete());
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null)
    {
        // Detail upserts could target a sealed row by detail ID with a different journal ID.
        throw new RuntimeException('Journal upsert is prohibited; use explicit protected insert/update.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        if (is_callable($values)) {
            throw new RuntimeException('Callable journal updateOrInsert is prohibited.');
        }

        return $this->connection->transaction(function () use ($attributes, $values) {
            $target = (clone $this)->where($attributes)->lockForUpdate();
            if ($target->exists()) {
                return $target->update($values);
            }

            return $this->insert(array_merge($attributes, $values));
        });
    }

    public function insertUsing(array $columns, $query)
    {
        throw new RuntimeException('Journal insertUsing is prohibited.');
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new RuntimeException('Journal insertOrIgnoreUsing is prohibited.');
    }

    public function truncate()
    {
        throw new RuntimeException('Journal truncate is prohibited.');
    }
}

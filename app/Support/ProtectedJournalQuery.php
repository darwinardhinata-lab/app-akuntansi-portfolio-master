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

        return $this->periodWrite(fn () => parent::insert($values), $values, true);
    }

    public function insertOrIgnore(array $values)
    {
        $this->checkRows($values);

        return $this->periodWrite(fn () => parent::insertOrIgnore($values), $values, true);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->checkRows($values);

        return $this->periodWrite(fn () => parent::insertGetId($values, $sequence), $values, true);
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

        return $this->periodWrite(fn () => parent::update($values), $values);
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

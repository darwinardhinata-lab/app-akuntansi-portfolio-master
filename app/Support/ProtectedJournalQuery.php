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

        return parent::insert($values);
    }

    public function insertOrIgnore(array $values)
    {
        $this->checkRows($values);

        return parent::insertOrIgnore($values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->checkRows($values);

        return parent::insertGetId($values, $sequence);
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

        return parent::update($values);
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            throw new RuntimeException('Use explicit journal filter rather than delete(id).');
        }
        $this->checkTargets();

        return parent::delete();
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

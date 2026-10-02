<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class JournalDetailBuilder extends Builder
{
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new RuntimeException('Journal upsert is prohibited; use protected writes.');
    }

    public function update(array $values)
    {
        MaklunJournalProtection::check((clone $this)->pluck('journal_id')->all());
        if (isset($values['journal_id'])) {
            MaklunJournalProtection::check([$values['journal_id']]);
        }

        return parent::update($values);
    }

    public function delete()
    {
        MaklunJournalProtection::check((clone $this)->pluck('journal_id')->all());

        return parent::delete();
    }

    public function insert(array $values)
    {
        foreach (isset($values[0]) && is_array($values[0]) ? $values : ($values ? [$values] : []) as $row) {
            MaklunJournalProtection::check(isset($row['journal_id']) ? [$row['journal_id']] : []);
            if (empty($row['account_code'])) {
                throw new RuntimeException('Mapping COA belum ditetapkan; posting dibatalkan.');
            }
        }

        return $this->toBase()->insert($values);
    }
}

<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class JournalHeaderBuilder extends Builder
{
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new \RuntimeException('Journal upsert is prohibited; use protected writes.');
    }

    public function update(array $values)
    {
        MaklunJournalProtection::check((clone $this)->pluck('journal_id')->all());

        return parent::update($values);
    }

    public function delete()
    {
        MaklunJournalProtection::check((clone $this)->pluck('journal_id')->all());

        return parent::delete();
    }
}

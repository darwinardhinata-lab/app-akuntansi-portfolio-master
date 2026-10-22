<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiAccountLedgerExport implements WithMultipleSheets
{
    public function __construct(private array $codes, private string $startDate, private string $endDate) {}

    public function sheets(): array
    {
        return array_map(fn ($code) => new LedgerExport($code, $this->startDate, $this->endDate), $this->codes);
    }
}
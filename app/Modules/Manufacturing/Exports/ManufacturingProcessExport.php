<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ManufacturingProcessExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Nama Proses', 'Tipe', 'Satuan Rate', 'Rate (Rp)', 'Vendor Default', 'Status'];
    }

    public function map($process): array
    {
        return [
            $process->process_name,
            $process->process_type,
            $process->rate_unit,
            $process->process_rate,
            $process->defaultSupplier->supplier_name ?? '-',
            $process->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

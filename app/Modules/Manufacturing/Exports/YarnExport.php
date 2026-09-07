<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class YarnExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return ['Kode Yarn', 'Jenis', 'Count', 'Komposisi', 'Warna', 'Satuan', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($yarn): array
    {
        return [
            $yarn->yarn_code,
            $yarn->yarn_type,
            $yarn->yarn_count ?? '-',
            $yarn->composition ?? '-',
            $yarn->color ?? '-',
            $yarn->unit,
            $yarn->stock_quantity,
            $yarn->average_cost,
            $yarn->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

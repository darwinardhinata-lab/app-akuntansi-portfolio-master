<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class FabricExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return ['Kode Fabric', 'Jenis', 'Subtype', 'State', 'GSM', 'Komposisi', 'Lebar', 'Warna', 'Satuan', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($fabric): array
    {
        return [
            $fabric->fabric_code,
            $fabric->fabric_type,
            $fabric->subtype ?? '-',
            $fabric->state,
            $fabric->gsm ?? '-',
            $fabric->composition ?? '-',
            $fabric->width ?? '-',
            $fabric->color ?? '-',
            $fabric->unit,
            $fabric->stock_quantity,
            $fabric->average_cost,
            $fabric->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

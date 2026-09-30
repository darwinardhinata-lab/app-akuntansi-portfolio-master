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
        return ['HS Code', 'Kode Bahan', 'Description', 'Nama Bahan', 'Nama Material Inggris', 'Kategori', 'Warna', 'Spesifikasi/Deskripsi', 'Satuan', 'Meter per Gulung', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($yarn): array
    {
        return [
            $yarn->hs_code ?? '-',
            $yarn->yarn_code,
            $yarn->description ?? $yarn->yarn_type,
            $yarn->material_name ?? '-',
            $yarn->english_name ?? '-',
            $yarn->category ?? '-',
            $yarn->color ?? '-',
            $yarn->specification ?? '-',
            $yarn->unit,
            $yarn->meters_per_roll ?? '-',
            $yarn->stock_quantity,
            $yarn->average_cost,
            $yarn->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

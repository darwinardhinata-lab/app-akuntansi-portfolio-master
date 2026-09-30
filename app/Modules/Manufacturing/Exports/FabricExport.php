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
        return ['HS Code', 'Kode Bahan', 'Description', 'Nama Bahan', 'Nama Material Inggris', 'Kategori', 'Warna', 'Spesifikasi/Deskripsi', 'Satuan', 'Meter per Gulung', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($fabric): array
    {
        return [
            $fabric->hs_code ?? '-',
            $fabric->fabric_code,
            $fabric->description ?? $fabric->fabric_type,
            $fabric->material_name ?? '-',
            $fabric->english_name ?? '-',
            $fabric->category ?? '-',
            $fabric->color ?? '-',
            $fabric->specification ?? '-',
            $fabric->unit,
            $fabric->meters_per_roll ?? '-',
            $fabric->stock_quantity,
            $fabric->average_cost,
            $fabric->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuxiliaryMaterialExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private $query) {}
    public function query() { return $this->query; }
    public function headings(): array { return ['HS Code', 'Kode Bahan', 'Description', 'Nama Bahan', 'Nama Material Inggris', 'Kategori', 'Warna', 'Spesifikasi/Deskripsi', 'Satuan', 'Meter per Gulung', 'Stok', 'HPP Rata-rata (Rp)', 'Status']; }
    public function map($material): array { return [$material->hs_code ?? '-', $material->material_code, $material->description ?? '-', $material->material_name, $material->english_name ?? '-', $material->category ?? '-', $material->color ?? '-', $material->specification ?? '-', $material->unit, $material->meters_per_roll ?? '-', $material->stock_quantity, $material->average_cost, $material->is_active ? 'AKTIF' : 'NONAKTIF']; }
}
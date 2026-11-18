<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class AuxiliaryMaterialExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private $query, private bool $lookup = false) {}
    public function query() { return $this->query; }
    public function headings(): array
    {
        if ($this->lookup) {
            return ['material_code', 'material_name', 'english_name', 'category', 'description',
                'color', 'specification', 'hs_code', 'unit', 'meters_per_roll', 'stock_quantity', 'is_active'];
        }

        return ['HS Code', 'Kode Bahan', 'Description', 'Nama Bahan', 'Nama Material Inggris', 'Kategori', 'Warna', 'Spesifikasi/Deskripsi', 'Satuan', 'Meter per Gulung', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($material): array
    {
        if ($this->lookup) {
            return array_map(fn (string $field) => $material->getAttribute($field) === null
                ? null : (is_bool($material->getAttribute($field))
                    ? ($material->getAttribute($field) ? '1' : '0') : (string) $material->getAttribute($field)), $this->headings());
        }

        return [$material->hs_code ?? '-', $material->material_code, $material->description ?? '-', $material->material_name, $material->english_name ?? '-', $material->category ?? '-', $material->color ?? '-', $material->specification ?? '-', $material->unit, $material->meters_per_roll ?? '-', $material->stock_quantity, $material->average_cost, $material->is_active ? 'AKTIF' : 'NONAKTIF'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class FabricExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder
{
    protected $query;

    public function __construct($query, private bool $lookup = false)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        if ($this->lookup) {
            return ['fabric_code', 'material_name', 'english_name', 'category', 'description',
                'fabric_type', 'subtype', 'state', 'color', 'specification', 'composition',
                'hs_code', 'width', 'gsm', 'unit', 'meters_per_roll', 'stock_quantity',
                'inventory_account_code', 'is_active'];
        }
        return ['HS Code', 'Kode Bahan', 'Description', 'Nama Bahan', 'Nama Material Inggris', 'Kategori', 'Warna', 'Spesifikasi/Deskripsi', 'Satuan', 'Meter per Gulung', 'Stok', 'HPP Rata-rata (Rp)', 'Status'];
    }

    public function map($fabric): array
    {
        if ($this->lookup) {
            return array_map(fn (string $field) => $fabric->getAttribute($field) === null
                ? null : (string) $fabric->getAttribute($field), $this->headings());
        }
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

    public function bindValue(Cell $cell, $value): bool
    {
        // Same interchange contract as YarnExport/AccountExport: never evaluate text.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

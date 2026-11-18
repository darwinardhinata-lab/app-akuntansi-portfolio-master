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

class YarnExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder
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

    public function bindValue(Cell $cell, $value): bool
    {
        // Match AccountExport: formula-like master values, leading zeros and
        // DECIMAL casts are interchange text, never executable spreadsheet input.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

<?php

namespace App\Modules\CustomsReports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Template (header-only) untuk import laporan WIP / Posisi.
 */
class PosisiTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Satuan Barang',
            'Jumlah Barang',
            'Keterangan',
        ];
    }

    public function array(): array
    {
        return [];
    }
}
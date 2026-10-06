<?php

namespace App\Modules\CustomsReports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Template (header-only) untuk import laporan mutasi.
 */
class MutasiTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Satuan',
            'Saldo Awal',
            'Pemasukan',
            'Pengeluaran',
            'Penyesuaian',
            'Saldo Akhir',
            'Stock Opname',
            'Selisih',
            'Keterangan',
        ];
    }

    public function array(): array
    {
        return [];
    }
}
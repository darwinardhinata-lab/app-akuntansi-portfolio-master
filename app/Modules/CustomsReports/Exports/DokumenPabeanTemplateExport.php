<?php

namespace App\Modules\CustomsReports\Exports;

use App\Modules\CustomsReports\Models\ReportPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Template (header-only) untuk import laporan dokumen pabean.
 * Header persis sama dengan file XLS asli agar user tidak bingung.
 */
class DokumenPabeanTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    private ReportPeriod $reportPeriod;

    public function __construct(ReportPeriod $reportPeriod)
    {
        $this->reportPeriod = $reportPeriod;
    }

    public function headings(): array
    {
        return [
            'Jenis Dokumen Pabean',
            'No. Pendaftaran Dokumen Pabean',
            'Tgl. Dokumen Pabean',
            $this->reportPeriod->noBuktiLabel(),
            'Tgl. Bukti',
            $this->reportPeriod->pihakTerkaitLabel(),
            'Kode Barang',
            'Nama Barang',
            'Jumlah Barang',
            'Satuan Barang',
            'Mata Uang',
            'Nilai',
            'Seri Faktur Pajak',
            'Nilai Faktur Pajak',
        ];
    }

    public function array(): array
    {
        return [];
    }
}
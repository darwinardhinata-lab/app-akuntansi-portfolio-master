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
        // Flat, unambiguous headings for import; the screen uses grouped headings.
        $columns = \App\Modules\CustomsReports\Support\ReportLayout::columns($this->reportPeriod->report_type);
        unset($columns['no']);
        $columns['no_pendaftaran_dok_pabean'] = 'No. Pendaftaran Dokumen Pabean';
        $columns['tgl_dok_pabean'] = 'Tgl. Dokumen Pabean';
        $columns['no_bukti'] = $this->reportPeriod->noBuktiLabel();
        $columns['tgl_bukti'] = 'Tgl. Bukti';
        return array_values($columns);
    }

    public function array(): array
    {
        return [];
    }
}
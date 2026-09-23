<?php

namespace App\Modules\CustomsReports\Exports;

use App\Modules\CustomsReports\Models\PosisiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export laporan WIP / Posisi Barang Dalam Proses (#4).
 *
 * Header persis sesuai file XLS asli 101109.xls.
 */
class PosisiExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ReportPeriod $reportPeriod;

    public function __construct(ReportPeriod $reportPeriod)
    {
        $this->reportPeriod = $reportPeriod;
    }

    public function query()
    {
        return PosisiLine::where('report_period_id', $this->reportPeriod->id);
    }

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

    public function map($line): array
    {
        return [
            $line->kode_barang,
            $line->nama_barang,
            $line->satuan_barang,
            IndonesianNumberParser::format((float) $line->jumlah_barang, 2),
            $line->keterangan ?? '',
        ];
    }
}
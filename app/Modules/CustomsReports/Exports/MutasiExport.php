<?php

namespace App\Modules\CustomsReports\Exports;

use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export laporan mutasi (#3 Bahan Baku, #5 Barang Jadi, #6 Barang Modal, #7 Reject).
 *
 * Header persis sesuai file XLS asli, termasuk urutan kolom.
 */
class MutasiExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ReportPeriod $reportPeriod;

    public function __construct(ReportPeriod $reportPeriod)
    {
        $this->reportPeriod = $reportPeriod;
    }

    public function query()
    {
        return MutasiLine::where('report_period_id', $this->reportPeriod->id);
    }

    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Satuan Barang',
            'Jumlah Barang',
            'Saldo Awal',
            'Jumlah Pemasukan Barang',
            'Jumlah Pengeluaran Barang',
            'Penyesuaian/Adjustment',
            'Saldo Akhir',
            'Hasil Pencacahan',
            'Jumlah Selisih',
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
            IndonesianNumberParser::format((float) $line->saldo_awal, 2),
            IndonesianNumberParser::format((float) $line->jumlah_pemasukan_barang, 2),
            IndonesianNumberParser::format((float) $line->jumlah_pengeluaran_barang, 2),
            IndonesianNumberParser::format((float) $line->penyesuaian_adjustment, 2),
            IndonesianNumberParser::format((float) $line->saldo_akhir, 2),
            $line->hasil_pencacahan,
            IndonesianNumberParser::format((float) $line->jumlah_selisih, 2),
            $line->keterangan ?? '',
        ];
    }
}
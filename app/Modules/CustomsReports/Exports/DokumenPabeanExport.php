<?php

namespace App\Modules\CustomsReports\Exports;

use App\Modules\CustomsReports\Models\DokumenPabeanLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export laporan dokumen pabean (#1 Pemasukan, #2 Pengeluaran).
 *
 * Header persis sesuai file XLS asli, termasuk urutan kolom.
 * Label kolom no_bukti & pihak_terkait berubah sesuai report_type.
 */
class DokumenPabeanExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ReportPeriod $reportPeriod;

    public function __construct(ReportPeriod $reportPeriod)
    {
        $this->reportPeriod = $reportPeriod;
    }

    public function query()
    {
        return DokumenPabeanLine::where('report_period_id', $this->reportPeriod->id);
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

    public function map($line): array
    {
        return [
            $line->jenis_dok_pabean,
            $line->no_pendaftaran_dok_pabean,
            $line->tgl_dok_pabean ? $line->tgl_dok_pabean->format('d/m/Y') : '',
            $line->no_bukti,
            $line->tgl_bukti ? $line->tgl_bukti->format('d/m/Y') : '',
            $line->pihak_terkait,
            $line->kode_barang,
            $line->nama_barang,
            IndonesianNumberParser::format((float) $line->jumlah_barang, 2),
            $line->satuan_barang,
            $line->mata_uang,
            IndonesianNumberParser::format((float) $line->nilai, 4),
            $line->seri_faktur_pajak ?? '',
            $line->nilai_faktur_pajak ? IndonesianNumberParser::format((float) $line->nilai_faktur_pajak, 4) : '',
        ];
    }
}
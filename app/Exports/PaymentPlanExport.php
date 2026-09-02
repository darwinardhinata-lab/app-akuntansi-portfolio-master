<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PaymentPlanExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return [
            'Tgl Pengajuan', 'No Transaksi', 'Divisi', 'Kategori', 
            'Rekening Ops', 'Vendor / PJ', 'Keterangan', 'Nominal (Rp)', 'Status'
        ];
    }

    public function map($pp): array
    {
        return [
            \Carbon\Carbon::parse($pp->tgl_pengajuan)->format('d/m/Y'),
            $pp->no_transaksi,
            $pp->divisi->nama_divisi ?? '',
            $pp->kategori_payment,
            $pp->jenis_transaksi,
            $pp->vendor_toko . ' / ' . $pp->penerima_pj,
            $pp->keterangan,
            $pp->nominal,
            $pp->status_payment
        ];
    }
}
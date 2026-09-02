<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PaymentPlanManualWorklistExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No Transaksi (Ref ERP)', 'Tanggal', 'Kategori Payment', 'Rekening Ops', 'Status Payment',
            'Nama (PJ)', 'Divisi', 'Pemasok/Toko', 'Nama Toko / Link', 'Keterangan',
            'No VA/Rekening/Kode Bayar', 'Detil Akun (COA)', 'Qty', 'Satuan',
            'Pengajuan (Rp)', 'Aktual (Rp)', 'Selisih (Rp)', 'Total (Rp)',
            'No. Bill/PO Referensi', 'Sudah Diinput ke Jubelio? (Y/N)',
        ];
    }

    public function map($pp): array
    {
        $refDoc = $pp->ref_bill_number ?: ($pp->ref_po_number ?: '-');
        $detilAkun = optional($pp->account)->account_name ?? $pp->id_akun ?? '-';

        return [
            $pp->no_transaksi,
            \Carbon\Carbon::parse($pp->tgl_pengajuan)->format('d/m/Y'),
            $pp->kategori_payment,
            $pp->jenis_transaksi,
            $pp->status_payment,
            $pp->penerima_pj,
            $pp->divisi->nama_divisi ?? '-',
            $pp->vendor_toko,
            $pp->nama_toko_link ?? '-',
            $pp->keterangan,
            $pp->rekening_va ?? '-',
            $detilAkun,
            $pp->qty ?? 1,
            $pp->satuan ?? 'Pcs',
            (float) $pp->nominal,
            $pp->nominal_aktual !== null ? (float) $pp->nominal_aktual : null,
            $pp->selisih,
            $pp->nominal_aktual_efektif,
            $refDoc,
            'N',
        ];
    }
}
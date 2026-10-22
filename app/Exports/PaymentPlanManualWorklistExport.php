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
            __('accounting.text_dd2019aa1190'), __('erp.date'), __('accounting.text_fad8f060036c'), __('accounting.text_38a3151a123f'), __('accounting.text_5c0e39e73645'),
            __('accounting.text_0b58f36d8839'), __('erp.division_label'), __('accounting.text_219e0a4dda05'), __('accounting.text_686f018de41f'), __('erp.description'),
            __('accounting.text_0d5879cbb053'), __('accounting.text_c15bba863e5e'), __('erp.qty_caps'), __('erp.unit'),
            __('accounting.text_0321685f8cfe'), __('accounting.text_9d1b58523c1e'), __('accounting.text_f64dedd48aa9'), __('erp.total_rp_caps'),
            __('accounting.text_c2f642f6871d'), __('accounting.text_b5dde9e1bc2e'),
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

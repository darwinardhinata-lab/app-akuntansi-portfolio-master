<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class PaymentPlanKasBankJubelioExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings
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

    public function getCsvSettings(): array
    {
        // Jubelio mewajibkan delimiter koma pada file import Kas & Bank.
        return ['delimiter' => ','];
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Transaksi',
            'Tipe',
            'Kontak',
            'No Telp Kontak',
            'Akun Kas/Bank',
            'Nilai Kas/Bank',
            'Keterangan',
            'Detil Akun',
            'Nilai',
            'Keterangan',
        ];
    }

    public function map($pp): array
    {
        $rekeningMap = config('jubelio.rekening_map', []);
        
        $jenisTransaksiClean = trim($pp->jenis_transaksi ?? '');
        if (isset($rekeningMap[$jenisTransaksiClean])) {
            $akunKasBank = $rekeningMap[$jenisTransaksiClean];
        } elseif (!empty($jenisTransaksiClean) && $jenisTransaksiClean !== 'PENDING') {
            $akunKasBank = $jenisTransaksiClean;
        } else {
            $akunKasBank = 'Bank BCA BBW OPS';
        }

        $nilai = (float) $pp->nominal_aktual_efektif;

        $kontak = (!empty($pp->vendor_toko) && $pp->vendor_toko !== '-')
            ? $pp->vendor_toko
            : (!empty($pp->penerima_pj) && $pp->penerima_pj !== '-' ? $pp->penerima_pj : 'Umum');

        $tipeKasBank = strtoupper($pp->paymentCategory->tipe_kas_bank ?? '');
        if (empty($tipeKasBank)) {
            $tipeKasBank = strtoupper($pp->kategori_payment) === 'PB' ? 'PENERIMAAN' : 'PEMBAYARAN';
        }
        $tipe = $tipeKasBank === 'PENERIMAAN' ? 'Penerimaan' : 'Pembayaran';

        $detilAkun = optional($pp->account)->account_name 
            ?? optional($pp->account)->account_code 
            ?? (!empty($pp->id_akun) ? $pp->id_akun : 'Beban Operasional');

        $keteranganUtama = $pp->keterangan ?: '-';
        $keteranganTransaksi = $keteranganUtama . ' [Ref ERP: ' . $pp->no_transaksi . ']';

        return [
            config('jubelio.no_transaksi_default', '[auto]'),
            \Carbon\Carbon::parse($pp->tgl_transaksi ?? $pp->tgl_pengajuan)->format('Y-m-d'),
            $tipe,
            $kontak,
            '', // No Telp Kontak: tidak tersedia di ERP, kosongkan
            $akunKasBank,
            $nilai,
            $keteranganTransaksi,
            $detilAkun,
            $nilai,
            $keteranganUtama,
        ];
    }
}
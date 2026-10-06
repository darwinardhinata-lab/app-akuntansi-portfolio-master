<?php

namespace App\Modules\CustomsReports\Support;

use App\Modules\CustomsReports\Models\ReportPeriod;

/** Shared display/export contract for the IT Inventory reference layouts. */
class ReportLayout
{
    public static function columns(string $type): array
    {
        if ($type === ReportPeriod::TYPE_WIP) {
            return ['no' => 'No', 'kode_barang' => 'Kode Barang', 'nama_barang' => 'Nama Barang',
                'satuan_barang' => 'Satuan', 'jumlah_barang' => 'Jumlah', 'keterangan' => 'Keterangan'];
        }
        if (! in_array($type, [ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN], true)) {
            return ['no' => 'No', 'kode_barang' => 'Kode Barang', 'nama_barang' => 'Nama Barang',
                'satuan_barang' => 'Satuan', 'saldo_awal' => 'Saldo Awal',
                'jumlah_pemasukan_barang' => 'Pemasukan', 'jumlah_pengeluaran_barang' => 'Pengeluaran',
                'penyesuaian_adjustment' => 'Penyesuaian', 'saldo_akhir' => 'Saldo Akhir',
                'hasil_pencacahan' => 'Stock Opname', 'jumlah_selisih' => 'Selisih', 'keterangan' => 'Keterangan'];
        }
        $incoming = $type === ReportPeriod::TYPE_PEMASUKAN;
        $columns = ['no' => 'No', 'jenis_dok_pabean' => 'Jenis'];
        if ($incoming) {
            $columns['no_aju'] = 'No Aju';
        }
        $columns += ['no_pendaftaran_dok_pabean' => 'Nomor', 'tgl_dok_pabean' => 'Tanggal',
            'no_bukti' => 'Nomor', 'tgl_bukti' => 'Tanggal',
            'pihak_terkait' => $incoming ? 'Pemasok/Pengirim' : 'Penerima',
            'kode_barang' => 'Kode barang', 'nama_barang' => 'Nama barang',
            'jumlah_barang' => 'QTY', 'satuan_barang' => 'Unit', 'bruto' => 'Bruto', 'netto' => 'Netto',
            'nilai' => 'Nilai Barang', 'mata_uang' => 'Currency'];
        if ($incoming) {
            $columns['harga_idr'] = 'Harga IDR';
        }
        return $columns;
    }

    public static function numeric(string $field): bool
    {
        return in_array($field, ['jumlah_barang', 'saldo_awal', 'jumlah_pemasukan_barang',
            'jumlah_pengeluaran_barang', 'penyesuaian_adjustment', 'saldo_akhir',
            'jumlah_selisih', 'bruto', 'netto', 'nilai', 'harga_idr'], true);
    }

    public static function value($line, string $field, int $number): mixed
    {
        if ($field === 'no') {
            return $number;
        }
        $value = $line->{$field};
        if (in_array($field, ['tgl_dok_pabean', 'tgl_bukti'], true)) {
            return $value?->format('Y-m-d');
        }
        // Never present an unperformed physical count as a measured zero.
        if ($field === 'hasil_pencacahan' && in_array($value, ['Belum', 'Sudah', ''], true)) {
            return null;
        }
        return $value;
    }

    public static function row($line, string $type, int $number): array
    {
        return array_map(fn ($field) => self::value($line, $field, $number), array_keys(self::columns($type)));
    }
}
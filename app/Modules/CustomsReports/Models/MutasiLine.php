<?php

namespace App\Modules\CustomsReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Laporan Bea Cukai — MutasiLine
 * Tabel: cbr_mutasi_lines
 *
 * Format B — dipakai untuk laporan #3 (Bahan Baku), #5 (Barang Jadi),
 * #6 (Barang Modal), #7 (Reject). Dibedakan lewat report_period_id → report_type induknya.
 * Kolom persis sesuai file export asli 101037.xls / 101116.xls / 101139.xls / 101147.xls.
 */
class MutasiLine extends Model
{
    protected $table = 'cbr_mutasi_lines';

    protected $fillable = [
        'report_period_id',
        'kode_barang',
        'nama_barang',
        'satuan_barang',
        'jumlah_barang',
        'saldo_awal',
        'jumlah_pemasukan_barang',
        'jumlah_pengeluaran_barang',
        'penyesuaian_adjustment',
        'saldo_akhir',
        'hasil_pencacahan',
        'jumlah_selisih',
        'keterangan',
    ];

    protected $casts = [
        'jumlah_barang'           => 'decimal:2',
        'saldo_awal'              => 'decimal:2',
        'jumlah_pemasukan_barang'  => 'decimal:2',
        'jumlah_pengeluaran_barang' => 'decimal:2',
        'penyesuaian_adjustment'  => 'decimal:2',
        'saldo_akhir'             => 'decimal:2',
        'jumlah_selisih'          => 'decimal:2',
    ];

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class, 'report_period_id');
    }
}
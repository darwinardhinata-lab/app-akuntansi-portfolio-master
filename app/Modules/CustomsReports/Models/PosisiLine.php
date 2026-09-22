<?php

namespace App\Modules\CustomsReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Laporan Bea Cukai — PosisiLine
 * Tabel: cbr_posisi_lines
 *
 * Format C — dipakai untuk laporan #4 (WIP).
 * Kolom persis sesuai file export asli 101109.xls.
 */
class PosisiLine extends Model
{
    protected $table = 'cbr_posisi_lines';

    protected $fillable = [
        'report_period_id',
        'kode_barang',
        'nama_barang',
        'satuan_barang',
        'jumlah_barang',
        'keterangan',
    ];

    protected $casts = [
        'jumlah_barang' => 'decimal:2',
    ];

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class, 'report_period_id');
    }
}
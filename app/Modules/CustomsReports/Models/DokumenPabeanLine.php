<?php

namespace App\Modules\CustomsReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Laporan Bea Cukai — DokumenPabeanLine
 * Tabel: cbr_dokumen_pabean_lines
 *
 * Format A — dipakai untuk laporan #1 (Pemasukan) & #2 (Pengeluaran).
 * Kolom persis sesuai file export asli 100940.xls / 101029.xls.
 */
class DokumenPabeanLine extends Model
{
    protected $table = 'cbr_dokumen_pabean_lines';

    protected $fillable = [
        'report_period_id',
        'jenis_dok_pabean',
        'no_pendaftaran_dok_pabean',
        'tgl_dok_pabean',
        'no_bukti',
        'tgl_bukti',
        'pihak_terkait',
        'kode_barang',
        'nama_barang',
        'jumlah_barang',
        'satuan_barang',
        'mata_uang',
        'nilai',
        'seri_faktur_pajak',
        'nilai_faktur_pajak',
    ];

    protected $casts = [
        'tgl_dok_pabean'       => 'date',
        'tgl_bukti'            => 'date',
        'jumlah_barang'        => 'decimal:2',
        'nilai'                => 'decimal:4',
        'nilai_faktur_pajak'   => 'decimal:4',
    ];

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class, 'report_period_id');
    }
}
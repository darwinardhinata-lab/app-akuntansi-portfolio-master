<?php

namespace App\Modules\CustomsReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modul Laporan Bea Cukai — ReportPeriod
 * Tabel: cbr_report_periods
 *
 * Header periode laporan. Menentukan 7 jenis laporan melalui report_type.
 * RIWAYAT_AKTIVITAS TIDAK termasuk di sini (Fase B terpisah).
 */
class ReportPeriod extends Model
{
    public const TYPE_PEMASUKAN        = 'PEMASUKAN';
    public const TYPE_PENGELUARAN      = 'PENGELUARAN';
    public const TYPE_MUTASI_BAHAN_BAKU = 'MUTASI_BAHAN_BAKU';
    public const TYPE_WIP             = 'WIP';
    public const TYPE_MUTASI_BARANG_JADI = 'MUTASI_BARANG_JADI';
    public const TYPE_MUTASI_BARANG_MODAL = 'MUTASI_BARANG_MODAL';
    public const TYPE_MUTASI_REJECT   = 'MUTASI_REJECT';

    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_FINAL     = 'FINAL';
    public const STATUS_DIUNGGAH  = 'DIUNGGAH';

    protected $table = 'cbr_report_periods';

    protected $fillable = [
        'report_type',
        'periode_bulan',
        'periode_tahun',
        'status',
        'finalized_at',
        'uploaded_at',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'periode_bulan'  => 'integer',
        'periode_tahun'  => 'integer',
        'finalized_at'   => 'datetime',
        'uploaded_at'    => 'datetime',
    ];

    /**
     * Daftar semua 7 jenis laporan (bukan 8 — RIWAYAT_AKTIVITAS khusus Fase B).
     */
    public const ALL_TYPES = [
        self::TYPE_PEMASUKAN,
        self::TYPE_PENGELUARAN,
        self::TYPE_MUTASI_BAHAN_BAKU,
        self::TYPE_WIP,
        self::TYPE_MUTASI_BARANG_JADI,
        self::TYPE_MUTASI_BARANG_MODAL,
        self::TYPE_MUTASI_REJECT,
    ];

    /**
     * Label yang ramah pengguna untuk setiap report_type.
     */
    public const TYPE_LABELS = [
        self::TYPE_PEMASUKAN             => 'Laporan Pemasukan Barang',
        self::TYPE_PENGELUARAN           => 'Laporan Pengeluaran Barang',
        self::TYPE_MUTASI_BAHAN_BAKU     => 'Mutasi Bahan Baku & Penolong',
        self::TYPE_WIP                   => 'Posisi Barang Dalam Proses (WIP)',
        self::TYPE_MUTASI_BARANG_JADI    => 'Mutasi Barang Jadi',
        self::TYPE_MUTASI_BARANG_MODAL   => 'Mutasi Barang Modal & Lain',
        self::TYPE_MUTASI_REJECT         => 'Mutasi Barang Reject & Sisa',
    ];

    /**
     * Relasi lines() mengembalikan model yang benar tergantung report_type.
     * Format A (DokumenPabeanLine): Pemasukan & Pengeluaran
     * Format B (MutasiLine): Mutasi Bahan Baku, Barang Jadi, Barang Modal, Reject
     * Format C (PosisiLine): WIP
     */
    public function lines(): HasMany
    {
        return match ($this->report_type) {
            self::TYPE_PEMASUKAN, self::TYPE_PENGELUARAN => $this->hasMany(DokumenPabeanLine::class, 'report_period_id'),
            self::TYPE_MUTASI_BAHAN_BAKU, self::TYPE_MUTASI_BARANG_JADI,
            self::TYPE_MUTASI_BARANG_MODAL, self::TYPE_MUTASI_REJECT => $this->hasMany(MutasiLine::class, 'report_period_id'),
            self::TYPE_WIP => $this->hasMany(PosisiLine::class, 'report_period_id'),
            default => $this->hasMany(DokumenPabeanLine::class, 'report_period_id'),
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function isUploaded(): bool
    {
        return $this->status === self::STATUS_DIUNGGAH;
    }

    /**
     * Label UI yang dinamis untuk kolom no_bukti — berubah tergantung
     * apakah ini laporan Pemasukan atau Pengeluaran.
     */
    public function noBuktiLabel(): string
    {
        return match ($this->report_type) {
            self::TYPE_PEMASUKAN   => 'No. Bukti Penerimaan Barang',
            self::TYPE_PENGELUARAN => 'No. Bukti Pengeluaran',
            default => 'No. Bukti',
        };
    }

    /**
     * Label UI yang dinamis untuk kolom pihak_terkait.
     */
    public function pihakTerkaitLabel(): string
    {
        return match ($this->report_type) {
            self::TYPE_PEMASUKAN   => 'Pengirim Barang',
            self::TYPE_PENGELUARAN => 'Penerima Barang',
            default => 'Pihak Terkait',
        };
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->report_type] ?? $this->report_type;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    protected $table = 'transaksi_payment_plan';
    protected $primaryKey = 'id_payment';

    protected $fillable = [
        'no_transaksi',
        'id_divisi',
        'id_akun',
        'tgl_pengajuan',
        'tgl_transaksi',
        'jatuh_tempo',
        'jenis_transaksi',
        'kategori_payment',
        'vendor_toko',
        'penerima_pj',
        'rekening_va',
        'keterangan',
        'nominal',
        'status_payment',
        'bukti_payment',
        'bukti_file',
        'ref_bill_number',
        'ref_po_number',
        'nama_toko_link',
        'qty',
        'satuan',
        'harga_satuan',
        'nominal_aktual',
    ];

    public function divisi()
    {
        return $this->belongsTo(MasterDivisi::class, 'id_divisi', 'id_divisi');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'id_akun', 'account_code');
    }

    public function paymentCategory()
    {
        return $this->belongsTo(PaymentCategory::class, 'kategori_payment', 'name');
    }

    public function details()
    {
        return $this->hasMany(PaymentPlanDetail::class, 'id_payment', 'id_payment');
    }

    /**
     * Hitung ulang nominal (& nominal_aktual, qty) di header berdasarkan SUM item detail.
     * Dipanggil setiap kali detail berubah (create/update/delete), supaya semua kode lama
     * yang baca $payment->nominal / $payment->nominal_aktual_efektif tetap benar
     * tanpa perlu tahu soal tabel detail.
     */
    public function recalcFromDetails(): void
    {
        $details = $this->details()->get();

        $totalNominal = (float) $details->sum('nominal');
        $totalQty     = (float) $details->sum('qty');

        // nominal_aktual header: NULL kalau semua detail belum diisi aktualnya,
        // kalau sudah ada minimal satu diisi -> SUM(COALESCE(nominal_aktual, nominal))
        $adaAktualDiisi = $details->contains(fn ($d) => $d->nominal_aktual !== null);
        $totalAktual = $adaAktualDiisi
            ? (float) $details->sum(fn ($d) => $d->nominal_aktual_efektif)
            : null;

        $this->forceFill([
            'nominal'        => $totalNominal,
            'qty'            => $totalQty,
            'nominal_aktual' => $totalAktual,
        ])->saveQuietly();
    }

    // TOTAL (Rp): nilai final yang dipakai untuk export.
    public function getNominalAktualEfektifAttribute()
    {
        return $this->nominal_aktual !== null ? (float) $this->nominal_aktual : (float) $this->nominal;
    }

    // SELISIH (Rp): Pengajuan - Aktual efektif. 0 selama Aktual belum diisi/sama.
    public function getSelisihAttribute()
    {
        return (float) $this->nominal - $this->nominal_aktual_efektif;
    }
}
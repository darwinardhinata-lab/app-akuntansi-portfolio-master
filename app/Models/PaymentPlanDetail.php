<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlanDetail extends Model
{
    protected $table = 'transaksi_payment_plan_detail'; // <-- WAJIB ada baris ini
    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_payment',
        'nama_item',
        'qty',
        'harga_satuan',
        'satuan',
        'keterangan',
        'bukti_file',
        'nominal',
        'nominal_aktual',
    ];

    protected $casts = [
        'qty'            => 'float',
        'harga_satuan'   => 'float',
        'nominal'        => 'float',
        'nominal_aktual' => 'float',
    ];

    public function paymentPlan()
    {
        return $this->belongsTo(PaymentPlan::class, 'id_payment', 'id_payment');
    }

    public function getNominalAktualEfektifAttribute()
    {
        return $this->nominal_aktual !== null ? (float) $this->nominal_aktual : (float) $this->nominal;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDivisi extends Model
{
    protected $table = 'master_divisi';
    protected $primaryKey = 'id_divisi';

    protected $fillable = [
        'kode_divisi',
        'nama_divisi',
        'status_aktif',
    ];

    public function paymentPlans()
    {
        return $this->hasMany(PaymentPlan::class, 'id_divisi', 'id_divisi');
    }
}

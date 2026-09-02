<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    // Pengaturan Primary Key
    protected $primaryKey = 'account_code';
    public $incrementing = false;
    protected $keyType = 'string';

    // Sesuaikan dengan kolom aktual di database
    protected $fillable = [
        'account_code',
        'account_name',
        'coa_type',       // SEBELUMNYA: type
        'normal_balance', 
        'report_pos'      // SEBELUMNYA: report_type
    ];

    /**
     * Relasi ke detail jurnal.
     */
    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'account_code', 'account_code');
    }
}
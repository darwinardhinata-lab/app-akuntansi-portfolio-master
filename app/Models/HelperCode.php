<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelperCode extends Model
{
    // Pengaturan Primary Key karena menggunakan string (helper_code)
    protected $primaryKey = 'helper_code';
    public $incrementing = false;
    protected $keyType = 'string';

    // B5 FIX: Ganti $guarded=[] (semua kolom terbuka) → $fillable eksplisit.
    // Dengan PK string 'helper_code', $guarded=[] memungkinkan request
    // menimpa primary key secara tidak sengaja via mass assignment.
    protected $fillable = [
        'helper_code',
        'entity_name',
        'marketing_name',
        'normal_balance',
    ];

    /**
     * Relasi ke detail jurnal.
     * Satu kode bantu bisa dikaitkan dengan banyak baris detail jurnal.
     */
    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'helper_code', 'helper_code');
    }
}
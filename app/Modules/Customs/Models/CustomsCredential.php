<?php

namespace App\Modules\Customs\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Kepabeanan — CustomsCredential
 * Tabel: cst_customs_credentials
 * 
 * Nilai sensitif (api_secret, cert_password) dienkripsi via Laravel's encrypted cast.
 */
class CustomsCredential extends Model
{
    protected $table = 'cst_customs_credentials';

    protected $fillable = [
        'environment',
        'api_key',
        'api_secret',
        'cert_path',
        'cert_password',
    ];

    protected $casts = [
        'api_secret' => 'encrypted',
        'cert_password' => 'encrypted',
    ];
}


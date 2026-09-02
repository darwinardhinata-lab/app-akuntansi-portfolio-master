<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_name', 'logo', 'npwp', 'website', 'address', 
        'province', 'city', 'country', 'postal_code', 'phone', 'email'
    ];
}
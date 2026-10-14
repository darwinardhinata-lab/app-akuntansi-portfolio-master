<?php

// FIX: multicurrency — kode ISO menjadi primary key; base tetap berasal dari companies.
namespace App\Modules\Currency\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'cur_currencies';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'active', 'show'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'active' => 'boolean', 'show' => 'boolean'];
    }
}
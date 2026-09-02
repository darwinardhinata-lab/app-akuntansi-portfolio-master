<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $table = 'journal_details';
    
    // Primary Key dari Detail Jurnal
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int'; 

    protected $fillable = [
        'journal_id',
        'account_code',
        'helper_code',
        'description',
        'position',
        'amount',
        'account_id',  // digunakan oleh ProcessPendingTempJob
        'journal_no',  // digunakan oleh ProcessPendingTempJob
    ];

    // Relasi kembali ke Header Jurnal
    public function header()
    {
        return $this->belongsTo(JournalHeader::class, 'journal_id', 'journal_id');
    }

    // 👇 INI DIA RELASI YANG HILANG (INJEKSI BARU) 👇
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_code', 'account_code');
    }
    // 👆 --------------------------------------- 👆
}
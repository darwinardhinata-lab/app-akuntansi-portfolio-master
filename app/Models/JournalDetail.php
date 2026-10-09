<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected function newBaseQueryBuilder()
    {
        return \App\Support\ProtectedJournalQuery::table('journal_details');
    }

    protected static function booted(): void
    {
        static::creating(function (JournalDetail $detail) {
            \App\Support\MaklunJournalProtection::check([$detail->journal_id]);
            if (empty($detail->account_code)) {
                throw new \RuntimeException('Mapping COA belum ditetapkan; posting dibatalkan.');
            }
        });
        static::updating(function (JournalDetail $detail) {
            \App\Support\MaklunJournalProtection::check([$detail->getOriginal('journal_id'), $detail->journal_id]);
        });
        static::deleting(fn (JournalDetail $detail) => \App\Support\MaklunJournalProtection::check([$detail->journal_id]));
    }

    public function newEloquentBuilder($query)
    {
        return new \App\Support\JournalDetailBuilder($query);
    }

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
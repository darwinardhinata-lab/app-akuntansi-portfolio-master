<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — CuttingCheck
 * Tabel: mfg_cutting_checks
 */
class CuttingCheck extends Model
{
    protected $table = 'mfg_cutting_checks';

    protected $fillable = [
        'cutting_order_id',
        'check_date',
        'pieces_cut',
        'pieces_ok',
        'pieces_rejected',
        'fabric_used_kg',
        'fabric_wastage_kg',
        'scrap_kg',
        'scrap_unit_value',
        'scrap_value_amount',
        'wastage_cost_amount',
        'journal_id',
        'voided_at',
        'reversal_journal_id',
        'size_breakdown_actual',
        'checked_by',
        'remarks',
    ];

    protected $casts = [
        'check_date' => 'date',
        'voided_at' => 'datetime',
        'size_breakdown_actual' => 'array',
    ];


    public function cuttingOrder()
    {
        return $this->belongsTo(CuttingOrder::class, 'cutting_order_id');
    }

    public function journal()
    {
        return $this->belongsTo(\App\Models\JournalHeader::class, 'journal_id', 'journal_id');
    }

    public function reversalJournal()
    {
        return $this->belongsTo(\App\Models\JournalHeader::class, 'reversal_journal_id', 'journal_id');
    }

}

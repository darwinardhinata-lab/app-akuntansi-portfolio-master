<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

class AuxiliaryMaterialIssue extends Model
{
    protected $table = 'mfg_auxiliary_material_issues';

    protected $fillable = ['issue_number', 'issue_date', 'auxiliary_material_id', 'work_order_id', 'line_id', 'usage_type', 'qty', 'unit_cost', 'total_cost', 'journal_id', 'voided_at', 'reversal_journal_id', 'remarks', 'created_by'];

    protected $casts = ['issue_date' => 'date', 'voided_at' => 'datetime'];

    public function material() { return $this->belongsTo(AuxiliaryMaterial::class, 'auxiliary_material_id'); }
    public function workOrder() { return $this->belongsTo(WorkOrder::class, 'work_order_id'); }
    public function journal() { return $this->belongsTo(\App\Models\JournalHeader::class, 'journal_id', 'journal_id'); }
    public function reversalJournal() { return $this->belongsTo(\App\Models\JournalHeader::class, 'reversal_journal_id', 'journal_id'); }
}
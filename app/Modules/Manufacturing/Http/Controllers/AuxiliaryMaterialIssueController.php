<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Services\AuxiliaryMaterialIssueService;
use Illuminate\Http\Request;

class AuxiliaryMaterialIssueController extends Controller
{
    public function __construct(private readonly AuxiliaryMaterialIssueService $service) {}
    public function store(Request $request)
    {
        $data = $request->validate(['issue_date'=>'required|date','auxiliary_material_id'=>'required|exists:mfg_auxiliary_materials,id','work_order_id'=>'nullable|exists:mfg_work_orders,id','line_id'=>'nullable|exists:mfg_production_lines,id','usage_type'=>'required|in:WIP,EXPENSE','qty'=>'required|numeric|min:0.01','remarks'=>'nullable|string']);
        try { $issue = $this->service->issue($data + ['created_by'=>auth()->id()]); SystemLog::record('POST','Manufacturing Auxiliary Material Issue','Issue '.$issue->issue_number); return $data['work_order_id'] ? redirect()->route('mfg.work-orders.show', $data['work_order_id'])->with('success', 'Issue Bahan Penolong berhasil diposting.') : redirect()->route('mfg.auxiliary-materials.index')->with('success', 'Issue Bahan Penolong berhasil diposting.'); }
        catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }
    public function void(int $id)
    {
        try { $this->service->void($id); return back()->with('success', 'Issue Bahan Penolong dibatalkan dengan jurnal reversal.'); }
        catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }
}
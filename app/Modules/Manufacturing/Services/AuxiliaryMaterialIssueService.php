<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\AuxiliaryMaterialIssue;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use App\Modules\Platform\Support\CompanyCoaResolver;
use App\Modules\Platform\Support\OperationalCompany;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use Exception;
use Illuminate\Support\Facades\DB;

class AuxiliaryMaterialIssueService
{
    public function issue(array $data): AuxiliaryMaterialIssue
    {
        return DB::transaction(function () use ($data) {
            \App\Support\AccountingPeriodGuard::source([$data['issue_date'] ?? null]);
            $usageType = $data['usage_type'];
            if (! in_array($usageType, ['WIP', 'EXPENSE'], true)) throw new Exception('Tujuan pemakaian bahan penolong tidak valid.');
            if ($usageType === 'WIP' && empty($data['work_order_id'])) throw new Exception('Issue ke WIP wajib memilih SPK.');
            $material = AuxiliaryMaterial::whereKey($data['auxiliary_material_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $workOrder = !empty($data['work_order_id']) ? WorkOrder::lockForUpdate()->findOrFail($data['work_order_id']) : null;
            if ($workOrder && $data['line_id'] && (int) $workOrder->line_id !== (int) $data['line_id']) throw new Exception('Line Produksi issue harus sama dengan Line pada SPK.');
            $company = app(OperationalCompany::class)->company(); $coa = app(CompanyCoaResolver::class);
            $inventory = $coa->account($company, 'auxiliary_material_inventory');
            $debit = $usageType === 'WIP' ? $coa->account($company, 'wip_inventory') : $coa->account($company, 'auxiliary_material_expense');
            $issueNumber = DocumentSequence::generateSecure('mfg_auxiliary_material_issues', 'issue_number', 'AMI-'.now()->format('Ymd').'-');
            $result = MaterialCostHelper::issueStock($material, 'AUXILIARY', (float) $data['qty'], $data['issue_date'], $issueNumber, "Issue bahan penolong {$usageType}: {$material->material_code}");
            $now = now();
            $journal = JournalHeader::create(['transaction_date' => $data['issue_date'], 'evidence_number' => $issueNumber, 'notes' => "Issue bahan penolong {$material->material_code} ({$usageType})", 'transaction_type' => 'Auxiliary Material Issue (MFG)']);
            $rows = [
                ['journal_id'=>$journal->getKey(),'account_code'=>$debit,'helper_code'=>null,'position'=>'DEBET','amount'=>$result['total_cost'],'created_at'=>$now,'updated_at'=>$now],
                ['journal_id'=>$journal->getKey(),'account_code'=>$inventory,'helper_code'=>null,'position'=>'KREDIT','amount'=>$result['total_cost'],'created_at'=>$now,'updated_at'=>$now],
            ];
            if (! JournalBalanceValidator::isBalanced($rows)) throw new Exception('Jurnal issue bahan penolong tidak balance.');
            JournalDetail::insert($rows);
            $issue = AuxiliaryMaterialIssue::create(['issue_number'=>$issueNumber,'issue_date'=>$data['issue_date'],'auxiliary_material_id'=>$material->id,'work_order_id'=>$workOrder?->id,'line_id'=>$data['line_id'] ?? $workOrder?->line_id,'usage_type'=>$usageType,'qty'=>$data['qty'],'unit_cost'=>$result['unit_cost'],'total_cost'=>$result['total_cost'],'journal_id'=>$journal->getKey(),'remarks'=>$data['remarks'] ?? null,'created_by'=>$data['created_by'] ?? null]);
            if ($usageType === 'WIP') WorkOrderService::accumulateCost($workOrder->id, materialCost: $result['total_cost']);
            return $issue;
        });
    }

    public function void(int $issueId): bool
    {
        return DB::transaction(function () use ($issueId) {
            $issue = AuxiliaryMaterialIssue::lockForUpdate()->findOrFail($issueId);
            if ($issue->voided_at) throw new Exception('Issue bahan penolong ini sudah dibatalkan.');
            $material = AuxiliaryMaterial::lockForUpdate()->findOrFail($issue->auxiliary_material_id);
            $company = app(OperationalCompany::class)->company(); $coa = app(CompanyCoaResolver::class);
            $inventory = $coa->account($company, 'auxiliary_material_inventory');
            $credit = $issue->usage_type === 'WIP' ? $coa->account($company, 'wip_inventory') : $coa->account($company, 'auxiliary_material_expense');
            $now = now();
            $oldValue = (float) $material->stock_quantity * (float) $material->average_cost;
            $material->stock_quantity += (float) $issue->qty;
            $material->average_cost = $material->stock_quantity > 0 ? ($oldValue + (float) $issue->total_cost) / (float) $material->stock_quantity : 0;
            $material->save();
            \App\Modules\Manufacturing\Models\MaterialLedger::create(['transaction_date'=>$now->toDateString(),'evidence_number'=>$issue->issue_number.'-VOID','item_type'=>'AUXILIARY','item_id'=>$material->id,'type'=>'IN','qty'=>$issue->qty,'unit_cost'=>$issue->unit_cost,'total_cost'=>$issue->total_cost,'running_qty'=>$material->stock_quantity,'running_value'=>$material->stock_quantity*$material->average_cost,'moving_average_cost'=>$material->average_cost,'description'=>"Pembalikan issue bahan penolong {$issue->issue_number}"]);
            $journal = JournalHeader::create(['transaction_date'=>$now->toDateString(),'evidence_number'=>$issue->issue_number.'-VOID','notes'=>"Pembalikan issue bahan penolong {$issue->issue_number}",'transaction_type'=>'Auxiliary Material Issue Reversal (MFG)']);
            $rows = [
                ['journal_id'=>$journal->getKey(),'account_code'=>$inventory,'helper_code'=>null,'position'=>'DEBET','amount'=>$issue->total_cost,'created_at'=>$now,'updated_at'=>$now],
                ['journal_id'=>$journal->getKey(),'account_code'=>$credit,'helper_code'=>null,'position'=>'KREDIT','amount'=>$issue->total_cost,'created_at'=>$now,'updated_at'=>$now],
            ];
            if (! JournalBalanceValidator::isBalanced($rows)) throw new Exception('Jurnal pembalikan issue bahan penolong tidak balance.');
            JournalDetail::insert($rows);
            if ($issue->usage_type === 'WIP') WorkOrderService::accumulateCost($issue->work_order_id, materialCost: -1 * (float) $issue->total_cost);
            $issue->update(['voided_at'=>$now,'reversal_journal_id'=>$journal->getKey()]);
            return true;
        });
    }
}
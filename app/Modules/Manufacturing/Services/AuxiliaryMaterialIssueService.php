<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\AuxiliaryMaterialIssue;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Support\AuxiliaryStockValue;
use App\Modules\Platform\Support\CompanyCoaResolver;
use App\Modules\Platform\Support\OperationalCompany;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use Brick\Math\BigDecimal;
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
            [$stock, $value] = AuxiliaryStockValue::balance($material);
            $qty = AuxiliaryStockValue::decimal($data['qty']);
            $remaining = bcsub($stock, $qty, 2);
            if (BigDecimal::of((string) $data['qty'])->isGreaterThan($stock)) throw new Exception('Stok bahan penolong tidak mencukupi.');
            $rate = $material->average_cost;
            $amount = $remaining === '0.00' ? $value : AuxiliaryStockValue::product($qty, $rate);
            if (bccomp($amount, $value, 2) > 0) {
                throw new Exception('Nilai issue parsial melebihi saldo persediaan; aturan pembulatan perlu direkonsiliasi.');
            }
            AuxiliaryStockValue::record($material, $data['issue_date'], $issueNumber, 'OUT', $qty, $rate, $amount,
                $remaining, bcsub($value, $amount, 2), $remaining === '0.00' ? '0.00' : $rate,
                "Issue bahan penolong {$usageType}: {$material->material_code}");
            $now = now();
            $journal = JournalHeader::create(['transaction_date' => $data['issue_date'], 'evidence_number' => $issueNumber, 'notes' => "Issue bahan penolong {$material->material_code} ({$usageType})", 'transaction_type' => 'Auxiliary Material Issue (MFG)']);
            $rows = [
                ['journal_id'=>$journal->getKey(),'account_code'=>$debit,'helper_code'=>null,'position'=>'DEBET','amount'=>$amount,'created_at'=>$now,'updated_at'=>$now],
                ['journal_id'=>$journal->getKey(),'account_code'=>$inventory,'helper_code'=>null,'position'=>'KREDIT','amount'=>$amount,'created_at'=>$now,'updated_at'=>$now],
            ];
            if (! JournalBalanceValidator::isBalanced($rows)) throw new Exception('Jurnal issue bahan penolong tidak balance.');
            JournalDetail::insert($rows);
            $issue = AuxiliaryMaterialIssue::create(['issue_number'=>$issueNumber,'issue_date'=>$data['issue_date'],'auxiliary_material_id'=>$material->id,'work_order_id'=>$workOrder?->id,'line_id'=>$data['line_id'] ?? $workOrder?->line_id,'usage_type'=>$usageType,'qty'=>$qty,'unit_cost'=>$rate,'total_cost'=>$amount,'journal_id'=>$journal->getKey(),'remarks'=>$data['remarks'] ?? null,'created_by'=>$data['created_by'] ?? null]);
            if ($usageType === 'WIP') WorkOrderService::accumulateCost($workOrder->id, materialCost: (float) $amount);
            return $issue;
        });
    }

    public function void(int $issueId): bool
    {
        return DB::transaction(function () use ($issueId) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $issue = AuxiliaryMaterialIssue::lockForUpdate()->findOrFail($issueId);
            if ($issue->voided_at) throw new Exception('Issue bahan penolong ini sudah dibatalkan.');
            $material = AuxiliaryMaterial::lockForUpdate()->findOrFail($issue->auxiliary_material_id);
            $company = app(OperationalCompany::class)->company(); $coa = app(CompanyCoaResolver::class);
            $inventory = $coa->account($company, 'auxiliary_material_inventory');
            $credit = $issue->usage_type === 'WIP' ? $coa->account($company, 'wip_inventory') : $coa->account($company, 'auxiliary_material_expense');
            $now = now();
            \App\Support\AccountingPeriodGuard::source([$issue->issue_date->toDateString(), $now->toDateString()]);
            [$stock, $value, $last] = AuxiliaryStockValue::balance($material);
            if ($issue->usage_type === 'EXPENSE' && ($last?->type !== 'OUT' || $last->evidence_number !== $issue->issue_number)) {
                throw new Exception('Void ditolak: terdapat mutasi lanjutan pada material atau OUT asal tidak ditemukan.');
            }
            $original = MaterialLedger::where('item_type', 'AUXILIARY')->where('item_id', $material->id)
                ->where('type', 'OUT')->where('evidence_number', $issue->issue_number)->lockForUpdate()->get();
            if ($original->count() !== 1 || bccomp((string) $original->first()->qty, (string) $issue->qty, 2) !== 0
                || bccomp((string) $original->first()->total_cost, (string) $issue->total_cost, 2) !== 0) {
                throw new Exception('Ledger OUT asal issue tidak konsisten.');
            }
            $stock = bcadd($stock, (string) $issue->qty, 2);
            $value = bcadd($value, (string) $issue->total_cost, 2);
            AuxiliaryStockValue::record($material, $now->toDateString(), $issue->issue_number.'-VOID', 'IN',
                AuxiliaryStockValue::decimal($issue->qty), AuxiliaryStockValue::decimal($issue->unit_cost),
                AuxiliaryStockValue::decimal($issue->total_cost), $stock, $value, AuxiliaryStockValue::average($value, $stock),
                "Pembalikan issue bahan penolong {$issue->issue_number}");
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
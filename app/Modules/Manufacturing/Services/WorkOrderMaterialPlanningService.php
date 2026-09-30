<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\WorkOrderMaterialRequirement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkOrderMaterialPlanningService
{
    public function shortagePreview(WorkOrder $workOrder)
    {
        return $workOrder->materialRequirements->map(function (WorkOrderMaterialRequirement $requirement) {
            $material = $this->materialForRequirement($requirement);
            $stock = (float) ($material?->stock_quantity ?? 0);
            $required = (float) $requirement->qty_required;
            $shortage = max(0, $required - $stock);
            return (object) [
                'requirement' => $requirement, 'stock_available' => $stock, 'shortage_qty' => $shortage,
                'shortage_estimated_cost' => round($shortage * (float) $requirement->unit_cost_snapshot, 2),
                'material_active' => (bool) ($material?->is_active ?? false),
            ];
        });
    }

    public function comparison(WorkOrder $workOrder)
    {
        return $workOrder->materialRequirements->map(function (WorkOrderMaterialRequirement $requirement) use ($workOrder) {
            $actual = match ($requirement->item_type) {
                'YARN' => (object) ['qty' => DB::table('mfg_yarn_issues')->join('mfg_knit_orders', 'mfg_knit_orders.id', '=', 'mfg_yarn_issues.knit_order_id')->where('mfg_knit_orders.work_order_id', $workOrder->id)->where('mfg_yarn_issues.yarn_id', $requirement->yarn_id)->sum('mfg_yarn_issues.qty_issued'), 'cost' => DB::table('mfg_yarn_issues')->join('mfg_knit_orders', 'mfg_knit_orders.id', '=', 'mfg_yarn_issues.knit_order_id')->where('mfg_knit_orders.work_order_id', $workOrder->id)->where('mfg_yarn_issues.yarn_id', $requirement->yarn_id)->sum('mfg_yarn_issues.total_cost')],
                'FABRIC' => (object) ['qty' => DB::table('mfg_cutting_orders')->where('work_order_id', $workOrder->id)->where('fabric_id', $requirement->fabric_id)->where('status', '!=', 'CANCELED')->sum('fabric_qty_issued') + DB::table('mfg_fabric_issues')->join('mfg_processing_orders', 'mfg_processing_orders.id', '=', 'mfg_fabric_issues.processing_order_id')->where('mfg_processing_orders.work_order_id', $workOrder->id)->where('mfg_fabric_issues.fabric_id', $requirement->fabric_id)->sum('mfg_fabric_issues.qty_issued'), 'cost' => DB::table('mfg_cutting_orders')->where('work_order_id', $workOrder->id)->where('fabric_id', $requirement->fabric_id)->where('status', '!=', 'CANCELED')->sum('fabric_total_cost') + DB::table('mfg_fabric_issues')->join('mfg_processing_orders', 'mfg_processing_orders.id', '=', 'mfg_fabric_issues.processing_order_id')->where('mfg_processing_orders.work_order_id', $workOrder->id)->where('mfg_fabric_issues.fabric_id', $requirement->fabric_id)->sum('mfg_fabric_issues.total_cost')],
                'AUXILIARY' => (object) ['qty' => DB::table('mfg_auxiliary_material_issues')->where('work_order_id', $workOrder->id)->where('auxiliary_material_id', $requirement->auxiliary_material_id)->whereNull('voided_at')->sum('qty'), 'cost' => DB::table('mfg_auxiliary_material_issues')->where('work_order_id', $workOrder->id)->where('auxiliary_material_id', $requirement->auxiliary_material_id)->whereNull('voided_at')->sum('total_cost')],
            };
            $actualQty = (float) $actual->qty;
            return (object) ['requirement' => $requirement, 'actual_qty' => $actualQty, 'variance_qty' => (float) $requirement->qty_required - $actualQty, 'actual_cost' => round((float) $actual->cost, 2)];
        });
    }

    public function generateShortageRequest(WorkOrder $workOrder, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($workOrder, $actorId) {
            $open = MaterialPurchaseRequest::where('source_work_order_id', $workOrder->id)->whereIn('approval_status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->exists();
            if ($open) throw new RuntimeException('Masih ada Material PR aktif yang dibuat dari SPK ini.');
            $items = [];
            foreach ($workOrder->materialRequirements as $requirement) {
                $material = $this->materialForRequirement($requirement);
                $shortage = max(0, (float) $requirement->qty_required - (float) ($material?->stock_quantity ?? 0));
                if ($shortage <= 0 || ! $material?->is_active) continue;
                $items[] = ['item_type' => $requirement->item_type, 'yarn_id' => $requirement->yarn_id, 'fabric_id' => $requirement->fabric_id, 'auxiliary_material_id' => $requirement->auxiliary_material_id, 'item_name' => $requirement->item_name, 'qty' => $shortage, 'unit' => $requirement->unit, 'remarks' => 'Kekurangan BOM SPK '.$workOrder->spk_number];
            }
            if (! $items) throw new RuntimeException('Tidak ada kekurangan stok terhadap kebutuhan BOM SPK ini.');
            return app(MaterialProcurementService::class)->createRequest(['request_date' => now()->toDateString(), 'required_date' => $workOrder->target_date?->toDateString(), 'remarks' => 'Otomatis dari kekurangan BOM SPK '.$workOrder->spk_number, 'created_by' => $actorId, 'source_work_order_id' => $workOrder->id], $items);
        });
    }

    private function materialForRequirement(WorkOrderMaterialRequirement $requirement)
    {
        return match ($requirement->item_type) {
            'YARN' => \App\Modules\Manufacturing\Models\Yarn::find($requirement->yarn_id),
            'FABRIC' => \App\Modules\Manufacturing\Models\Fabric::find($requirement->fabric_id),
            'AUXILIARY' => \App\Modules\Manufacturing\Models\AuxiliaryMaterial::find($requirement->auxiliary_material_id),
        };
    }
}
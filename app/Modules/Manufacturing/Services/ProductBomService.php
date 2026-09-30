<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\WorkOrderMaterialRequirement;
use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductBomService
{
    public function save(int $productId, array $data): ProductBom
    {
        $material = $this->resolveMaterial($data['item_type'], (int) $data['material_id']);
        $materialIdColumn = $this->materialIdColumn($data['item_type']);

        return ProductBom::updateOrCreate([
            'product_id' => $productId,
            'item_type' => $data['item_type'],
            'yarn_id' => null,
            'fabric_id' => null,
            'auxiliary_material_id' => null,
            $materialIdColumn => $material->id,
        ], [
            'qty_per_unit' => $data['qty_per_unit'], 'waste_percent' => $data['waste_percent'] ?? 0,
            'remarks' => $data['remarks'] ?? null, 'is_active' => true,
        ]);
    }

    public function snapshotForWorkOrder(WorkOrder $workOrder): void
    {
        if (! $workOrder->product_id) return;
        $boms = ProductBom::where('product_id', $workOrder->product_id)->where('is_active', true)->get();
        foreach ($boms as $bom) {
            $material = $this->resolveBomMaterial($bom);
            $qty = round((float) $workOrder->planned_qty * (float) $bom->qty_per_unit * (1 + ((float) $bom->waste_percent / 100)), 6);
            WorkOrderMaterialRequirement::create([
                'work_order_id' => $workOrder->id, 'bom_id' => $bom->id, 'item_type' => $bom->item_type,
                $this->materialIdColumn($bom->item_type) => $material->id,
                'item_code' => $this->code($material, $bom->item_type), 'item_name' => $this->name($material, $bom->item_type), 'unit' => $material->unit,
                'qty_per_unit' => $bom->qty_per_unit, 'waste_percent' => $bom->waste_percent, 'qty_required' => $qty,
                'unit_cost_snapshot' => $material->average_cost, 'estimated_total_cost' => round($qty * (float) $material->average_cost, 2), 'remarks' => $bom->remarks,
            ]);
        }
    }

    public function resolveMaterial(string $type, int $id)
    {
        $class = match ($type) { 'YARN' => Yarn::class, 'FABRIC' => Fabric::class, 'AUXILIARY' => AuxiliaryMaterial::class, default => throw new InvalidArgumentException('Jenis bahan BOM tidak valid.') };
        $material = $class::where('is_active', true)->find($id);
        if (! $material) throw new InvalidArgumentException('Bahan BOM tidak ditemukan atau tidak aktif.');
        return $material;
    }

    private function resolveBomMaterial(ProductBom $bom) { return $this->resolveMaterial($bom->item_type, $bom->{$this->materialIdColumn($bom->item_type)}); }
    private function materialIdColumn(string $type): string { return match ($type) { 'YARN' => 'yarn_id', 'FABRIC' => 'fabric_id', 'AUXILIARY' => 'auxiliary_material_id' }; }
    private function code($material, string $type): string { return $material->{match ($type) { 'YARN' => 'yarn_code', 'FABRIC' => 'fabric_code', 'AUXILIARY' => 'material_code' }}; }
    private function name($material, string $type): string { return $material->{match ($type) { 'YARN' => 'description', 'FABRIC' => 'description', 'AUXILIARY' => 'material_name' }} ?? ($type === 'YARN' ? $material->yarn_type : $material->fabric_type); }
}
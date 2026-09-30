<?php

namespace App\Modules\Manufacturing\Imports;

use App\Models\Product;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithStartRow;
use RuntimeException;

class ProductBomImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    public function getCsvSettings(): array { return ['delimiter' => ';']; }
    public function startRow(): int { return 7; }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $offset => $row) {
            $sku = trim((string) ($row[0] ?? ''));
            if ($sku === '' || strtoupper($sku) === 'SKU BARANG JADI') continue;
            $type = strtoupper(trim((string) ($row[2] ?? '')));
            $materialCode = trim((string) ($row[3] ?? ''));
            $qty = $row[4] ?? null;
            if (! in_array($type, ['YARN', 'FABRIC', 'AUXILIARY'], true) || $materialCode === '' || ! is_numeric($qty) || (float) $qty <= 0) throw new RuntimeException('Baris '.($offset + 7).': jenis bahan, kode bahan, dan qty per unit harus valid.');
            $product = Product::where('sku', $sku)->first();
            if (! $product) throw new RuntimeException('Baris '.($offset + 7).": SKU barang jadi '{$sku}' tidak ditemukan.");
            $material = match ($type) { 'YARN' => Yarn::where('yarn_code', $materialCode)->where('is_active', true)->first(), 'FABRIC' => Fabric::where('fabric_code', $materialCode)->where('is_active', true)->first(), 'AUXILIARY' => AuxiliaryMaterial::where('material_code', $materialCode)->where('is_active', true)->first() };
            if (! $material) throw new RuntimeException('Baris '.($offset + 7).": bahan {$type} '{$materialCode}' tidak ditemukan atau tidak aktif.");
            $idColumn = match ($type) { 'YARN' => 'yarn_id', 'FABRIC' => 'fabric_id', 'AUXILIARY' => 'auxiliary_material_id' };
            $attributes = ['product_id' => $product->id, 'item_type' => $type, 'yarn_id' => null, 'fabric_id' => null, 'auxiliary_material_id' => null, $idColumn => $material->id];
            ProductBom::updateOrCreate($attributes, ['qty_per_unit' => $qty, 'waste_percent' => is_numeric($row[5] ?? null) ? $row[5] : 0, 'remarks' => trim((string) ($row[6] ?? '')) ?: null, 'is_active' => strtoupper(trim((string) ($row[7] ?? 'AKTIF'))) !== 'NONAKTIF']);
        }
    }
}
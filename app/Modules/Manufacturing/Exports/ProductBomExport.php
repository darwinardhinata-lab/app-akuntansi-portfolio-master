<?php

namespace App\Modules\Manufacturing\Exports;

use App\Modules\Manufacturing\Models\ProductBom;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductBomExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function query() { return ProductBom::with(['product', 'yarn', 'fabric', 'auxiliaryMaterial'])->orderBy('product_id')->orderBy('item_type'); }
    public function headings(): array { return ['SKU BARANG JADI', 'NAMA BARANG JADI', 'JENIS BAHAN', 'KODE BAHAN', 'QTY PER UNIT', 'WASTE %', 'CATATAN', 'STATUS']; }
    public function map($bom): array
    {
        $material = $bom->item_type === 'YARN' ? $bom->yarn : ($bom->item_type === 'FABRIC' ? $bom->fabric : $bom->auxiliaryMaterial);
        return [$bom->product?->sku, $bom->product?->name, $bom->item_type, $material?->yarn_code ?? $material?->fabric_code ?? $material?->material_code, $bom->qty_per_unit, $bom->waste_percent, $bom->remarks, $bom->is_active ? 'AKTIF' : 'NONAKTIF'];
    }
}
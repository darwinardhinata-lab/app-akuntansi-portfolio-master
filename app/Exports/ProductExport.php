<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'SKU / Item Code', 'Nama Produk', 'Varian', 'Kategori',
            'Harga Jual (Rp)', 'HPP / Average Cost (Rp)', 'Stok Aktual', 'Satuan'
        ];
    }

    public function map($product): array
    {
        return [
            $product->sku,
            $product->name,
            $product->variation ?? '-',
            $product->category_name ?? '-',
            $product->sell_price,
            $product->average_cost,
            $product->stock_quantity,
            $product->unit
        ];
    }
}
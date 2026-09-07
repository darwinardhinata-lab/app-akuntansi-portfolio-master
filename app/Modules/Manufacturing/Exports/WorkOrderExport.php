<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WorkOrderExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return ['No. SPK', 'Tanggal', 'Garmen', 'Style SKU', 'Qty Rencana', 'Biaya Bahan (Rp)', 'Biaya Proses (Rp)', 'Total WIP (Rp)', 'Status'];
    }

    public function map($wo): array
    {
        return [
            $wo->spk_number,
            \Carbon\Carbon::parse($wo->order_date)->format('d-m-Y'),
            $wo->garment_name,
            $wo->style_sku ?? '-',
            $wo->planned_qty,
            $wo->total_material_cost,
            $wo->total_process_cost,
            $wo->total_wip_cost,
            $wo->status,
        ];
    }
}

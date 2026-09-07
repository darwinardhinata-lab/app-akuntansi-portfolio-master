<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Beda dgn export lain (FromQuery) — data laporan HPP sudah berupa hasil
 * kalkulasi (Collection of stdClass dari ManufacturingReportController::hpp()),
 * bukan Eloquent query mentah, jadi pakai FromCollection.
 */
class ManufacturingHppExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['No. SPK', 'Selesai', 'Garmen', 'Style SKU', 'Biaya Bahan (Rp)', 'Biaya Proses (Rp)', 'Wastage (Rp)', 'Total HPP (Rp)', 'Qty Jadi', 'HPP/Pcs (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row->work_order->spk_number,
            \Carbon\Carbon::parse($row->completed_at)->format('d-m-Y'),
            $row->work_order->garment_name,
            $row->work_order->style_sku ?? '-',
            $row->material_cost,
            $row->process_cost,
            $row->wastage_cost,
            $row->total_wip,
            $row->qty_finished,
            $row->unit_cost,
        ];
    }
}

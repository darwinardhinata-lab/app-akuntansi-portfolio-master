<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseOrderExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            'Tanggal PO', 'Nomor PO', 'Supplier', 'Lokasi', 
            'Status', 'Subtotal (Rp)', 'Pajak Penambah (Rp)', 
            'Pajak Pemotong (Rp)', 'Grand Total (Rp)'
        ];
    }

    public function map($po): array
    {
        return [
            \Carbon\Carbon::parse($po->transaction_date)->format('d/m/Y'),
            $po->po_number,
            $po->contact_name,
            $po->location_name ?? 'Pusat',
            $po->status,
            $po->sub_total,
            $po->tax_addition_amount,
            $po->tax_deduction_amount,
            $po->grand_total
        ];
    }
}
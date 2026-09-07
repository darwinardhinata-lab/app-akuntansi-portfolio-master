<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MaterialReceiptExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return ['No. MRN', 'Tanggal', 'Supplier', 'No. Dokumen Supplier', 'Bruto (Rp)', 'Pajak (Rp)', 'Neto (Rp)', 'Status'];
    }

    public function map($receipt): array
    {
        return [
            $receipt->receipt_number,
            \Carbon\Carbon::parse($receipt->receipt_date)->format('d-m-Y'),
            $receipt->supplier->supplier_name ?? '-',
            $receipt->supplier_doc_no ?? '-',
            $receipt->gross_amount,
            $receipt->tax_amount,
            $receipt->net_amount,
            $receipt->status,
        ];
    }
}

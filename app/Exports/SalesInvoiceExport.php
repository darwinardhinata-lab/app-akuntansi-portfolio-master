<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesInvoiceExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            'Tanggal Faktur', 'Nomor Faktur', 'Ref. SO', 'Pelanggan', 
            'Status Bayar', 'Subtotal (Rp)', 'Diskon (Rp)', 
            'Pajak (Rp)', 'Ongkir (Rp)', 'Grand Total (Rp)'
        ];
    }

    public function map($inv): array
    {
        return [
            \Carbon\Carbon::parse($inv->transaction_date)->format('d/m/Y'),
            $inv->invoice_number,
            $inv->salesOrder ? $inv->salesOrder->so_number : '-',
            $inv->contact_name,
            $inv->payment_status,
            $inv->sub_total,
            $inv->disc_amount,
            $inv->tax_amount,
            $inv->shipping_cost,
            $inv->grand_total
        ];
    }
}
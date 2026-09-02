<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesOrderExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            'Tanggal SO', 'Nomor SO', 'Pelanggan', 'Toko / Lokasi', 
            'Status', 'Item Dipesan', 'Subtotal (Rp)', 'Diskon (Rp)', 
            'Pajak (Rp)', 'Ongkir (Rp)', 'Grand Total (Rp)'
        ];
    }

    public function map($so): array
    {
        return [
            \Carbon\Carbon::parse($so->transaction_date)->format('d/m/Y'),
            $so->so_number,
            $so->contact_name,
            $so->store_name ?? ($so->location_name ?? 'Pusat'),
            $so->status,
            $so->details->sum('qty'),
            $so->sub_total,
            $so->disc_amount + $so->other_discount,
            $so->tax_amount,
            $so->shipping_cost - $so->shipping_discount,
            $so->grand_total
        ];
    }
}
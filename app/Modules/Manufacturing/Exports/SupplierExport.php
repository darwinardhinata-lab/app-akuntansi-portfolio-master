<?php

namespace App\Modules\Manufacturing\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SupplierExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return ['Kode Supplier', 'Nama', 'Tipe', 'Kode Bantu (AP)', 'Kontak', 'Telepon', 'Email', 'Alamat', 'NPWP', 'Termin', 'Status'];
    }

    public function map($supplier): array
    {
        return [
            $supplier->supplier_code,
            $supplier->supplier_name,
            $supplier->supplier_type,
            $supplier->helper_code ?? '-',
            $supplier->contact_person ?? '-',
            $supplier->phone ?? '-',
            $supplier->email ?? '-',
            $supplier->address ?? '-',
            $supplier->npwp ?? '-',
            $supplier->payment_terms ?? '-',
            $supplier->is_active ? 'AKTIF' : 'NONAKTIF',
        ];
    }
}

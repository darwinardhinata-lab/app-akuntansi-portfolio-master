<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountExport implements FromQuery, WithHeadings
{
    use Exportable;

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $search = $this->request->get('search');
        $coa_type = $this->request->get('coa_type');
        $report_pos = $this->request->get('report_pos');

        $query = Account::query();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('account_code', 'like', '%' . $search . '%')
                  ->orWhere('account_name', 'like', '%' . $search . '%');
            });
        }
        
        if (!empty($coa_type)) {
            $query->where('coa_type', $coa_type);
        }
        
        if (!empty($report_pos)) {
            $query->where('report_pos', $report_pos);
        }

        return $query->orderBy('account_code', 'asc');
    }

    public function headings(): array
    {
        return [
            'Kode Akun',
            'Nama Akun',
            'Tipe COA',
            'Pos Saldo',
            'Pos Laporan',
            'Created At',
            'Updated At',
        ];
    }
}

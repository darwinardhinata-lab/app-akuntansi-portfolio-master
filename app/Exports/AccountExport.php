<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Illuminate\Http\Request;

class AccountExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithCustomValueBinder
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
            ['DAFTAR AKUN (COA)'],
            ['Data dimulai dari baris ke-6. Nilai master asli digunakan untuk impor ulang.'],
            ['Kolom B: Kode, Kolom C: Nama, Kolom D: Tipe, Kolom E: Saldo, Kolom F: Laporan'],
            [''],
            ['NO', 'KODE AKUN', 'NAMA AKUN', 'TIPE COA', 'POS SALDO', 'POS LAPORAN', 'Created At', 'Updated At'],
        ];
    }

    // This workbook is an importable interchange format, not a translated report.
    public function map($account): array
    {
        return [
            null,
            $account->account_code,
            $account->account_name,
            $account->coa_type,
            $account->normal_balance,
            $account->report_pos,
            optional($account->created_at)->format('Y-m-d H:i:s'),
            optional($account->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Preserve leading zeros and render formula-like master text literally.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

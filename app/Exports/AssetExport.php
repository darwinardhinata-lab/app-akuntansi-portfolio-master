<?php

namespace App\Exports;

use App\Models\Asset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class AssetExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $search;

    public function __construct($startDate = null, $endDate = null, $search = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
    }

    public function collection()
    {
        $query = Asset::with('journalDetail.header.details.account')
            ->orderBy('purchase_date', 'desc');

        if (!empty($this->startDate)) {
            $query->whereDate('purchase_date', '>=', $this->startDate);
        }
        if (!empty($this->endDate)) {
            $query->whereDate('purchase_date', '<=', $this->endDate);
        }
        if (!empty($this->search)) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('asset_code', 'like', '%' . $searchTerm . '%')
                  ->orWhere('asset_name', 'like', '%' . $searchTerm . '%');
            });
        }

        $assets = $query->get();
        $today = Carbon::now();
        $no = 0;

        return $assets->map(function ($asset) use ($today, &$no) {
            $no++;
            $depreciationPerMonth = 0;
            $accumulated = 0;
            $bookValue = $asset->purchase_price;

            if ($asset->useful_life_months > 0) {
                $start = Carbon::parse($asset->purchase_date);
                $diff = $start->diffInMonths($today);
                $age = min(max(0, $diff), $asset->useful_life_months);
                $depreciableAmount = $asset->purchase_price - $asset->residual_value;
                $depreciationPerMonth = $depreciableAmount / $asset->useful_life_months;
                $accumulated = $age * $depreciationPerMonth;
                $bookValue = $asset->purchase_price - $accumulated;
            }

            return [
                $no,
                $asset->asset_code,
                $asset->asset_name,
                $asset->category ?? '-',
                $asset->quantity ?? 1,
                Date::stringToExcel($asset->purchase_date),
                $asset->purchase_price,
                $accumulated,
                $bookValue,
                $asset->residual_value ?? 0,
                $asset->is_active ? 'Aktif' : 'Tidak Aktif',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Aset',
            'Nama Aset',
            'Kategori',
            'Qty',
            'Tgl. Pemakaian',
            'Nilai Perolehan',
            'Akumulasi Penyusutan',
            'Nilai Buku',
            'Nilai Sisa',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:K' . $sheet->getHighestRow() => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}
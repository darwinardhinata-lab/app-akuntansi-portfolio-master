<?php

namespace App\Exports;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Support\ProtectedJournalQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $accCode;
    protected $startDate;
    protected $endDate;
    protected $account;
    protected $isDebetNormal;
    protected $openingBalance;

    public function __construct($accCode, $startDate, $endDate)
    {
        $this->accCode   = $accCode;
        $this->startDate = $startDate;
        $this->endDate   = $endDate;

        $this->account = Account::where('account_code', $accCode)->first();

        $prefix = substr(trim($accCode), 0, 1);
        if ($this->account && !empty($this->account->normal_balance)) {
            $this->isDebetNormal = strtoupper(trim($this->account->normal_balance)) === 'DEBET';
        } else {
            $this->isDebetNormal = in_array($prefix, ['1', '5', '6', '9']);
        }

        // Saldo Awal sebelum startDate
        $openingDebet = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->where('account_code', $accCode)
            ->where('position', 'DEBET')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $openingKredit = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->where('account_code', $accCode)
            ->where('position', 'KREDIT')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $baseOpening = $this->isDebetNormal ? ($openingDebet - $openingKredit) : ($openingKredit - $openingDebet);
        $this->openingBalance = round($baseOpening, 2);
    }

    public function headings(): array
    {
        $accTitle = 'KARTU AKUN: ' . ($this->account ? ($this->account->account_code . ' - ' . $this->account->account_name) : $this->accCode);
        $periodTitle = 'Periode: ' . date('d M Y', strtotime($this->startDate)) . ' s/d ' . date('d M Y', strtotime($this->endDate));
        $posisiTitle = 'Saldo Normal: ' . ($this->isDebetNormal ? 'DEBET' : 'KREDIT');

        return [
            [$accTitle],
            [$periodTitle . ' | ' . $posisiTitle],
            [''],
            [
                'Tanggal',
                'No. Bukti / No. Transaksi',
                'Keterangan Transaksi',
                'Debet (Rp)',
                'Kredit (Rp)',
                'Saldo Akhir (Rp)',
            ],
        ];
    }

    public function array(): array
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', 600);
            @ini_set('memory_limit', '1024M');
        }

        $rows = [];

        // Baris Saldo Awal
        $rows[] = [
            date('d M Y', strtotime($this->startDate)),
            '-',
            'SALDO AWAL (Sebelum ' . date('d/m/Y', strtotime($this->startDate)) . ')',
            '-',
            '-',
            number_format($this->openingBalance, 2, ',', '.'),
        ];

        $runningBalance = $this->openingBalance;

        // Ambil semua transaksi pada rentang periode
        $transactions = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->select(
                'journal_details.*',
                'journal_headers.transaction_date',
                'journal_headers.evidence_number',
                'journal_headers.source_doc_no',
                'journal_headers.notes'
            )
            ->where('account_code', $this->accCode)
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('journal_details.id', 'asc')
            ->get();

        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($transactions as $tx) {
            $deb = $tx->position === 'DEBET' ? (float) $tx->amount : 0;
            $kre = $tx->position === 'KREDIT' ? (float) $tx->amount : 0;

            if ($this->isDebetNormal) {
                $runningBalance += ($deb - $kre);
            } else {
                $runningBalance += ($kre - $deb);
            }

            $totalDebet += $deb;
            $totalKredit += $kre;

            $bukti = filled($tx->evidence_number) ? $tx->evidence_number : $tx->journal_id;
            if (filled($tx->source_doc_no)) {
                $bukti .= ' (Trx: ' . $tx->source_doc_no . ')';
            }

            $rows[] = [
                date('d M Y', strtotime($tx->transaction_date)),
                $bukti,
                $tx->notes ?? '-',
                $deb > 0 ? number_format($deb, 2, ',', '.') : '-',
                $kre > 0 ? number_format($kre, 2, ',', '.') : '-',
                number_format($runningBalance, 2, ',', '.'),
            ];
        }

        // Baris Total Mutasi & Saldo Akhir
        $rows[] = [
            'TOTAL MUTASI',
            '',
            '',
            number_format($totalDebet, 2, ',', '.'),
            number_format($totalKredit, 2, ',', '.'),
            number_format($runningBalance, 2, ',', '.'),
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '555555']]],
            4 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
            ],
            $highestRow => ['font' => ['bold' => true]],
            'A4:F' . $highestRow => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCBD5E1'],
                    ],
                ],
            ],
        ];
    }
}

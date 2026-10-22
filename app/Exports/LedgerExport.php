<?php

namespace App\Exports;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Support\ProtectedJournalQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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
        $accTitle = __('accounting.text_fee3d064ead0') . ($this->account ? ($this->account->account_code . ' - ' . $this->account->translatedName()) : $this->accCode);
        $periodTitle = __('accounting.text_acfb45b8495a') . date('d M Y', strtotime($this->startDate)) . __('erp.to_abbr') . date('d M Y', strtotime($this->endDate));
        $posisiTitle = __('accounting.text_2037a4309708') . ($this->isDebetNormal ? __('erp.debit_caps') : __('erp.credit_caps'));

        return [
            [$accTitle],
            [$periodTitle . ' | ' . $posisiTitle],
            [''],
            [
                __('erp.date'),
                __('accounting.text_9c87d44f85d1'),
                __('accounting.text_57d5fe80f49c'),
                __('erp.debit_rp'),
                __('erp.credit_rp'),
                __('accounting.text_d629df3fcf34'),
            ],
        ];
    }

    public function title(): string
    {
        return substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '_', $this->accCode), 0, 31);
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
            __('accounting.text_1b459d366cb8', ['date' => date('d/m/Y', strtotime($this->startDate))]),
            '-',
            '-',
            number_format($this->openingBalance, 2, ',', '.'),
        ];

        $runningBalance = $this->openingBalance;

        // Ambil semua transaksi pada rentang periode
        $transactions = JournalDetail::query()
            ->when(config('libretranslate.enabled'), fn ($query) => $query->with('header.noteTranslations'))
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
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
                (config('libretranslate.enabled') ? $tx->header?->display_notes : $tx->notes) ?? '-',
                $deb > 0 ? number_format($deb, 2, ',', '.') : '-',
                $kre > 0 ? number_format($kre, 2, ',', '.') : '-',
                number_format($runningBalance, 2, ',', '.'),
            ];
        }

        // Baris Total Mutasi & Saldo Akhir
        $rows[] = [
            __('accounting.text_646c504ba4a8'),
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

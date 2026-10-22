<?php

namespace App\Exports;

use App\Models\JournalHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JournalExport implements FromArray, WithHeadings, WithStyles, WithBatchInserts, WithChunkReading, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $search;
    protected $accountCodes;

    public function __construct($startDate = null, $endDate = null, $search = null, array $accountCodes = [])
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->accountCodes = $accountCodes;
    }

    public function array(): array
    {
        // Increase execution time and memory for large exports
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', 600);  // Ditingkatkan dari 300 ke 600 detik
            @ini_set('memory_limit', '1024M');     // Ditingkatkan dari 512M ke 1024M
        }

        // Build the query for journal headers only (without eager loading)
        $headerQuery = JournalHeader::orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');
        if ($this->accountCodes) {
            $headerQuery->whereHas('details', fn ($detail) => $detail->whereIn('account_code', $this->accountCodes));
        }
        if (config('libretranslate.enabled')) {
            $headerQuery->with('noteTranslations');
        }

        if (!empty($this->startDate)) {
            $headerQuery->whereDate('transaction_date', '>=', $this->startDate);
        }

        if (!empty($this->endDate)) {
            $headerQuery->whereDate('transaction_date', '<=', $this->endDate);
        }

        if (!empty($this->search)) {
            $headerQuery->where(function($q) {
                $q->where('evidence_number', 'like', '%' . $this->search . '%')
                  ->orWhere('notes', 'like', '%' . $this->search . '%')
                  ->orWhereHas('details', function($qDet) {
                      $qDet->where('account_code', 'like', '%' . $this->search . '%')
                           ->orWhereHas('account', function($qAcc) {
                               $qAcc->where('account_name', 'like', '%' . $this->search . '%');
                           });
                  });
            });
        }

        // Limit total export to prevent timeout (set to 0 for no limit - exports ALL pages)
        // Export mengambil SEMUA data yang lolos filter (All Pages), bukan hanya Page 1
        $limit = 0; // 0 = No limit, export semua data
        
        $rows = [];
        $processedCount = 0;

        // Process in chunks to avoid memory issues
        // Chunk size dinaikkan untuk performa yang lebih baik
        $headerQuery->chunk(500, function ($journals) use (&$rows, &$processedCount, $limit) {
            if ($limit > 0 && $processedCount >= $limit) {
                return false; // Stop chunking
            }

            // Collect all journal IDs for batch loading of details
            $journalIds = $journals->pluck('journal_id')->toArray();

            // Batch load all details with their accounts for these journals
            $details = \App\Support\ProtectedJournalQuery::table('journal_details')
                ->leftJoin('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
                ->whereIn('journal_details.journal_id', $journalIds)
                ->select(
                    'journal_details.journal_id',
                    'journal_details.account_code',
                    'accounts.account_name',
                    'journal_details.helper_code',
                    'journal_details.position',
                    'journal_details.amount'
                )
                ->orderBy('journal_details.id', 'asc')
                ->get()
                ->groupBy('journal_id');

            $accountNames = \App\Models\Account::with('translations')
                ->whereIn('account_code', $details->flatten(1)->pluck('account_code')->unique())->get()
                ->mapWithKeys(fn ($account) => [$account->account_code => $account->translatedName()]);

            foreach ($journals as $journal) {
                if ($limit > 0 && $processedCount >= $limit) {
                    break;
                }

                $journalDetails = $details->get($journal->journal_id, collect());
                $totalDebet = $journalDetails->filter(fn ($detail) => strtoupper($detail->position) === 'DEBET')->sum('amount');
                $totalKredit = $journalDetails->filter(fn ($detail) => strtoupper($detail->position) === 'KREDIT')->sum('amount');

                foreach ($journalDetails as $detail) {
                    $rows[] = [
                        date('d M Y', strtotime($journal->transaction_date)),
                        $journal->journal_no ?: $journal->evidence_number,
                        $journal->source_doc_no ?: $journal->evidence_number,
                        $journal->display_notes,
                        number_format($totalDebet, 2, ',', '.'),
                        number_format($totalKredit, 2, ',', '.'),
                        number_format(strtoupper($detail->position) === 'DEBET' ? $detail->amount : 0, 2, ',', '.'),
                        number_format(strtoupper($detail->position) === 'KREDIT' ? $detail->amount : 0, 2, ',', '.'),
                        $detail->account_code . (($name = $accountNames->get($detail->account_code, $detail->account_name)) ? ' - ' . $name : '')
                    ];
                }

                $processedCount++;
            }
        });

        return $rows;
    }

    /**
     * Batch size for inserts
     */
    public function batchSize(): int
    {
        return 2000;  // Ditingkatkan dari 1000 untuk performa yang lebih baik
    }

    /**
     * Chunk size for reading
     */
    public function chunkSize(): int
    {
        return 2000;  // Ditingkatkan dari 1000 untuk performa yang lebih baik
    }

    public function headings(): array
    {
        return [
            __('erp.date'),
            __('accounting.text_fee1777b99a1'),
            __('accounting.text_8491ba819ad7'),
            __('accounting.text_7e9fd192194a'),
            __('accounting.text_d660e8d17c93'),
            __('accounting.text_e16136d28aef'),
            __('accounting.text_ccb1653283e1'),
            __('accounting.text_a2df1cc97ce7'),
            __('accounting.text_ffcd7fbae331')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

            // Style all cells with thin borders
            'A1:I' . $sheet->getHighestRow() => [
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
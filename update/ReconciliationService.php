<?php
// Simpan di: app/Services/ReconciliationService.php
// (menggantikan versi sebelumnya — menambahkan dukungan format native Jubelio)

namespace App\Services;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Support\AccountClassifier;
use App\Support\NumberParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ReconciliationService
 *
 * Membandingkan total per akun (prefix 4-9 / akun Laba Rugi) antara sumber
 * Jubelio dan ledger ERP (journal_headers + journal_details) untuk periode
 * yang sama.
 *
 * Mendukung DUA format file sumber:
 *
 *  - 'coded'  : CSV dengan kolom kode akun eksplisit
 *               (mis. "77920,Penyesuaian Persediaan Barang Toko Pakel +,481333.19")
 *               -> dicocokkan by kode akun (paling akurat).
 *
 *  - 'native' : Export/print asli Jubelio yang TIDAK punya kode akun,
 *               hanya "Nama Akun<pemisah>Nominal", dikelompokkan per section
 *               ("Pendapatan", "Harga Pokok Penjualan", "Biaya",
 *               "Pendapatan Lainnya", "Biaya Lainnya") dan diselingi baris
 *               subtotal ("Total Pendapatan", "Laba/Rugi Kotor", dst).
 *               -> dicocokkan by NAMA akun (exact, lalu fuzzy sebagai fallback).
 *
 * Default 'auto': format dideteksi otomatis dari isi file.
 */
class ReconciliationService
{
    protected float $defaultTolerance = 1000.00;

    /** Ambang batas kemiripan nama (0-100) untuk fallback fuzzy matching di format native. */
    protected float $fuzzyThreshold = 88.0;

    protected array $nativeSectionHeaders = [
        'pendapatan',
        'harga pokok penjualan',
        'biaya',
        'pendapatan lainnya',
        'biaya lainnya',
    ];

    protected array $nativeTotalKeywords = [
        'total pendapatan'             => 'Total Pendapatan',
        'total harga pokok penjualan'  => 'Total HPP',
        'total biaya'                  => 'Total Biaya',
        'laba/rugi kotor'              => 'Laba Kotor',
        'laba usaha'                   => 'Laba Usaha',
        'laba/rugi bersih'             => 'Laba Bersih',
    ];

    // ==================================================================
    // ENTRY POINT
    // ==================================================================

    public function reconcile(UploadedFile $file, string $startDate, string $endDate, ?float $tolerance = null, string $format = 'auto'): array
    {
        $tolerance = $tolerance ?? $this->defaultTolerance;

        if ($format === 'auto') {
            $format = $this->detectFormat($file);
        }

        return $format === 'native'
            ? $this->reconcileNative($file, $startDate, $endDate, $tolerance)
            : $this->reconcileCoded($file, $startDate, $endDate, $tolerance);
    }

    public function detectFormat(UploadedFile $file): string
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return 'native';
        }

        $checked = 0;
        while ($checked < 15 && ($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $checked++;

            $firstToken = preg_split('/[,;]/', $line)[0] ?? '';
            $firstToken = trim($firstToken);

            if (preg_match('/^\d{3,6}$/', $firstToken) || preg_match('/^\d{3,6}\s*[-–]/', $line)) {
                fclose($handle);
                return 'coded';
            }
        }

        fclose($handle);
        return 'native';
    }

    // ==================================================================
    // FORMAT: CODED (kode akun eksplisit di file)
    // ==================================================================

    public function reconcileCoded(UploadedFile $file, string $startDate, string $endDate, ?float $tolerance = null): array
    {
        $tolerance = $tolerance ?? $this->defaultTolerance;

        $jubelioData = $this->parseJubelioCsv($file);
        $erpData     = $this->getErpBalances($startDate, $endDate);

        $allCodes = collect($jubelioData->keys())->merge($erpData->keys())->unique()->sort()->values();

        $rows = [];
        $totalAbsSelisih = 0.0;
        $matchCount = 0;
        $mismatchCount = 0;
        $missingInErpCount = 0;
        $missingInJubCount = 0;

        foreach ($allCodes as $code) {
            $jub = $jubelioData->get($code);
            $erp = $erpData->get($code);

            $jubAmount = $jub['amount'] ?? 0.0;
            $erpAmount = $erp['amount'] ?? 0.0;
            $accountName = $jub['account_name'] ?? $erp['account_name'] ?? 'Akun Tidak Dikenal';

            $selisih = round($erpAmount - $jubAmount, 2);
            $absSelisih = abs($selisih);

            if (is_null($jub)) {
                $status = 'TIDAK_ADA_DI_JUBELIO';
                $missingInJubCount++;
            } elseif (is_null($erp)) {
                $status = 'TIDAK_ADA_DI_ERP';
                $missingInErpCount++;
            } elseif ($absSelisih <= $tolerance) {
                $status = 'MATCH';
                $matchCount++;
            } else {
                $status = 'MISMATCH';
                $mismatchCount++;
            }

            if ($status !== 'MATCH') {
                $totalAbsSelisih += $absSelisih;
            }

            $rows[] = [
                'account_code' => $code,
                'account_name' => $accountName,
                'jubelio'      => round($jubAmount, 2),
                'erp'          => round($erpAmount, 2),
                'selisih'      => $selisih,
                'abs_selisih'  => $absSelisih,
                'status'       => $status,
                'match_type'   => 'KODE',
            ];
        }

        usort($rows, fn($a, $b) => $b['abs_selisih'] <=> $a['abs_selisih']);

        return [
            'rows'    => $rows,
            'summary' => [
                'format'                => 'coded',
                'period_start'          => $startDate,
                'period_end'            => $endDate,
                'tolerance'             => $tolerance,
                'total_akun'            => count($rows),
                'match_count'           => $matchCount,
                'mismatch_count'        => $mismatchCount,
                'missing_in_erp_count'  => $missingInErpCount,
                'missing_in_jub_count'  => $missingInJubCount,
                'fuzzy_count'           => 0,
                'total_abs_selisih'     => round($totalAbsSelisih, 2),
                'jubelio_grand_total'   => round($jubelioData->sum('amount'), 2),
                'erp_grand_total'       => round($erpData->sum('amount'), 2),
                'skipped_rows'          => $this->lastSkippedRows,
                'control_warnings'      => [],
            ],
        ];
    }

    protected int $lastSkippedRows = 0;

    public function parseJubelioCsv(UploadedFile $file): Collection
    {
        $this->lastSkippedRows = 0;
        $result = collect();

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return $result;
        }

        $firstLine = fgets($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $isFirstRow = true;

        while (($rawLine = fgets($handle)) !== false) {
            if (trim($rawLine) === '') {
                continue;
            }

            $cols = str_getcsv($rawLine, $delimiter, '"', '\\');
            $cols = array_map('trim', $cols);
            $cols = array_values(array_filter($cols, fn($c) => $c !== ''));

            if (count($cols) < 2) {
                $isFirstRow = false;
                continue;
            }

            if ($isFirstRow) {
                $isFirstRow = false;
                $looksLikeHeader = preg_match('/[a-zA-Z]/', $cols[0]) && !preg_match('/^\d/', $cols[0]);
                if ($looksLikeHeader) {
                    continue;
                }
            }

            [$code, $name] = $this->extractAccountCodeAndName($cols);
            if (!$code) {
                $this->lastSkippedRows++;
                continue;
            }

            $amount = $this->extractAmount($cols);
            if (is_null($amount)) {
                $this->lastSkippedRows++;
                continue;
            }

            if ($result->has($code)) {
                $existing = $result->get($code);
                $existing['amount'] += abs($amount);
                $result->put($code, $existing);
            } else {
                $result->put($code, [
                    'account_name' => $name,
                    'amount'       => abs($amount),
                ]);
            }
        }

        fclose($handle);

        return $result;
    }

    protected function extractAccountCodeAndName(array $cols): array
    {
        if (preg_match('/^\d{3,6}$/', $cols[0])) {
            $name = $cols[1] ?? '';
            if (preg_match('/^-?[\d.,\s]+$/', $name)) {
                $name = '';
            }
            return [$cols[0], $name];
        }

        if (preg_match('/^(\d{3,6})\s*[-–]?\s*(.*)$/', $cols[0], $m)) {
            return [$m[1], trim($m[2])];
        }

        return [null, null];
    }

    protected function extractAmount(array $cols): ?float
    {
        for ($i = count($cols) - 1; $i >= 0; $i--) {
            $val = $cols[$i];

            if (str_contains($val, '%')) {
                continue;
            }
            if (!preg_match('/\d/', $val)) {
                continue;
            }
            if ($val === $cols[0]) {
                continue;
            }

            $clean = trim(str_ireplace('Rp', '', $val));
            return NumberParser::parseDecimal($clean);
        }

        return null;
    }

    // ==================================================================
    // FORMAT: NATIVE (export/print asli Jubelio, tanpa kode akun)
    // ==================================================================

    public function reconcileNative(UploadedFile $file, string $startDate, string $endDate, ?float $tolerance = null): array
    {
        $tolerance = $tolerance ?? $this->defaultTolerance;

        $parsed   = $this->parseJubelioNativeExport($file);
        $jubItems = $parsed['items']; // normalized_name => ['original_name','amount','section']

        $erpByCode = $this->getErpBalances($startDate, $endDate); // code => ['account_name','amount']
        $erpByName = collect();
        foreach ($erpByCode as $code => $d) {
            $norm = $this->normalizeName($d['account_name']);
            $erpByName->put($norm, [
                'account_code' => $code,
                'account_name' => $d['account_name'],
                'amount'       => $d['amount'],
            ]);
        }

        $matchedErpNorms = [];
        $rows = [];
        $totalAbsSelisih = 0.0;
        $matchCount = 0;
        $mismatchCount = 0;
        $missingInErpCount = 0;
        $missingInJubCount = 0;
        $fuzzyCount = 0;

        foreach ($jubItems as $norm => $jub) {
            $erp = $erpByName->get($norm);
            $matchType = 'EXACT';

            if (!$erp) {
                [$erp, $bestNorm, $score] = $this->fuzzyFindErpMatch($norm, $erpByName, $matchedErpNorms);
                if ($erp) {
                    $matchType = 'FUZZY';
                    $fuzzyCount++;
                    $matchedErpNorms[] = $bestNorm;
                }
            } else {
                $matchedErpNorms[] = $norm;
            }

            $jubAmount = $jub['amount'];
            $erpAmount = $erp['amount'] ?? 0.0;
            $selisih = round($erpAmount - $jubAmount, 2);
            $absSelisih = abs($selisih);

            if (!$erp) {
                $status = 'TIDAK_ADA_DI_ERP';
                $missingInErpCount++;
            } elseif ($absSelisih <= $tolerance) {
                $status = 'MATCH';
                $matchCount++;
            } else {
                $status = 'MISMATCH';
                $mismatchCount++;
            }

            if ($status !== 'MATCH') {
                $totalAbsSelisih += $absSelisih;
            }

            $rows[] = [
                'account_code' => $erp['account_code'] ?? '-',
                'account_name' => $jub['original_name'],
                'jubelio'      => round($jubAmount, 2),
                'erp'          => round($erpAmount, 2),
                'selisih'      => $selisih,
                'abs_selisih'  => $absSelisih,
                'status'       => $status,
                'match_type'   => $matchType,
            ];
        }

        foreach ($erpByName as $norm => $erp) {
            if (in_array($norm, $matchedErpNorms, true)) {
                continue;
            }
            $amt = round($erp['amount'], 2);
            $rows[] = [
                'account_code' => $erp['account_code'],
                'account_name' => $erp['account_name'],
                'jubelio'      => 0.0,
                'erp'          => $amt,
                'selisih'      => $amt,
                'abs_selisih'  => abs($amt),
                'status'       => 'TIDAK_ADA_DI_JUBELIO',
                'match_type'   => '-',
            ];
            $missingInJubCount++;
            $totalAbsSelisih += abs($amt);
        }

        usort($rows, fn($a, $b) => $b['abs_selisih'] <=> $a['abs_selisih']);

        $controlWarnings = $this->buildControlWarnings($parsed);

        return [
            'rows'    => $rows,
            'summary' => [
                'format'                => 'native',
                'period_start'          => $startDate,
                'period_end'            => $endDate,
                'tolerance'             => $tolerance,
                'total_akun'            => count($rows),
                'match_count'           => $matchCount,
                'mismatch_count'        => $mismatchCount,
                'missing_in_erp_count'  => $missingInErpCount,
                'missing_in_jub_count'  => $missingInJubCount,
                'fuzzy_count'           => $fuzzyCount,
                'total_abs_selisih'     => round($totalAbsSelisih, 2),
                'jubelio_grand_total'   => round($jubItems->sum('amount'), 2),
                'erp_grand_total'       => round($erpByCode->sum('amount'), 2),
                'skipped_rows'          => $parsed['skipped_rows'],
                'control_warnings'      => $controlWarnings,
            ],
        ];
    }

    public function parseJubelioNativeExport(UploadedFile $file): array
    {
        $items = collect();
        $controlTotals = [];
        $sectionSums = [];
        $currentSection = null;
        $skipped = 0;

        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (str_contains($line, ';') || str_contains($line, "\t") || preg_match('/,\S/', $line)) {
                $delimiter = str_contains($line, ';') ? ';' : (str_contains($line, "\t") ? "\t" : ',');
                $cols = str_getcsv($line, $delimiter, '"', '\\');
                $cols = array_map('trim', $cols);
                $cols = array_values(array_filter($cols, fn($c) => $c !== ''));
                if (count($cols) >= 2) {
                    $line = implode(' ', $cols);
                } elseif (count($cols) === 1) {
                    $line = $cols[0];
                }
            }

            if (preg_match('/^laporan laba rugi$/i', $line)) {
                continue;
            }
            if (preg_match('/^\d{1,2}\s+\w+\s+\d{4}\s*-\s*\d{1,2}\s+\w+\s+\d{4}$/iu', $line)) {
                continue;
            }
            if (preg_match('/tgl\.?\s*cetak/i', $line)) {
                continue;
            }
            if (preg_match('/^hal:?\s*\d+\s*$/i', $line)) {
                continue;
            }

            $normLine = mb_strtolower($line);

            if (in_array($normLine, $this->nativeSectionHeaders, true)) {
                $currentSection = $line;
                $sectionSums[$currentSection] = $sectionSums[$currentSection] ?? 0.0;
                continue;
            }

            if (!preg_match('/(-?[\d.]+,\d{2})\s*$/', $line, $m)) {
                $skipped++;
                continue;
            }

            $amountStr = $m[1];
            $name = rtrim(substr($line, 0, strlen($line) - strlen($m[0])));

            if ($name === '') {
                $skipped++;
                continue;
            }

            $amount = NumberParser::parseDecimal($amountStr);
            $normName = mb_strtolower($name);

            $isTotalLine = false;
            foreach ($this->nativeTotalKeywords as $needle => $label) {
                if (str_starts_with($normName, $needle)) {
                    $controlTotals[$label] = $amount;
                    $isTotalLine = true;
                    break;
                }
            }
            if ($isTotalLine) {
                continue;
            }

            $normalized = $this->normalizeName($name);
            if ($items->has($normalized)) {
                $existing = $items->get($normalized);
                $existing['amount'] += abs($amount);
                $items->put($normalized, $existing);
            } else {
                $items->put($normalized, [
                    'original_name' => $name,
                    'amount'        => abs($amount),
                    'section'       => $currentSection,
                ]);
            }

            if ($currentSection) {
                $sectionSums[$currentSection] = ($sectionSums[$currentSection] ?? 0.0) + abs($amount);
            }
        }

        return [
            'items'          => $items,
            'control_totals' => $controlTotals,
            'section_sums'   => $sectionSums,
            'skipped_rows'   => $skipped,
        ];
    }

    protected function buildControlWarnings(array $parsed): array
    {
        $warnings = [];
        $sectionToControlKey = [
            'pendapatan'             => 'Total Pendapatan',
            'harga pokok penjualan'  => 'Total HPP',
            'biaya'                  => 'Total Biaya',
        ];

        foreach ($parsed['section_sums'] as $section => $sum) {
            $key = $sectionToControlKey[mb_strtolower($section)] ?? null;
            if (!$key || !isset($parsed['control_totals'][$key])) {
                continue;
            }
            $diff = round($sum - $parsed['control_totals'][$key], 2);
            if (abs($diff) > 10) {
                $warnings[] = sprintf(
                    "Bagian '%s': jumlah baris yang terbaca (Rp %s) tidak sama dengan total tercetak di file (Rp %s) — kemungkinan ada baris yang gagal di-parse.",
                    $section,
                    number_format($sum, 2, ',', '.'),
                    number_format($parsed['control_totals'][$key], 2, ',', '.')
                );
            }
        }

        return $warnings;
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);
        $name = rtrim($name, " \t\n\r\0\x0B+-");
        return trim($name);
    }

    protected function fuzzyFindErpMatch(string $normJub, Collection $erpByName, array $alreadyMatched): array
    {
        $bestScore = 0.0;
        $bestNorm = null;
        $bestData = null;

        foreach ($erpByName as $norm => $data) {
            if (in_array($norm, $alreadyMatched, true)) {
                continue;
            }
            similar_text($normJub, $norm, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $bestNorm = $norm;
                $bestData = $data;
            }
        }

        if ($bestData && $bestScore >= $this->fuzzyThreshold) {
            return [$bestData, $bestNorm, $bestScore];
        }

        return [null, null, 0.0];
    }

    // ==================================================================
    // SUMBER ERP (SAMA UNTUK KEDUA FORMAT)
    // ==================================================================

    public function getErpBalances(string $startDate, string $endDate): Collection
    {
        $data = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->select(
                DB::raw('TRIM(journal_details.account_code) as account_code'),
                'journal_details.position',
                DB::raw('SUM(journal_details.amount) as total')
            )
            ->whereIn(DB::raw('LEFT(TRIM(journal_details.account_code), 1)'), ['4', '5', '6', '7', '8', '9'])
            ->where('journal_headers.transaction_date', '>=', $startDate . ' 00:00:00')
            ->where('journal_headers.transaction_date', '<=', $endDate . ' 23:59:59')
            ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
            ->where('journal_headers.description', 'NOT LIKE', '%SETUP SALDO AWAL%')
            ->where(function ($q) {
                $q->whereNull('journal_headers.is_opening_balance')
                  ->orWhere('journal_headers.is_opening_balance', 0);
            })
            ->groupBy(DB::raw('TRIM(journal_details.account_code)'), 'journal_details.position')
            ->get();

        $masterAccounts = Account::whereIn(DB::raw('LEFT(TRIM(account_code), 1)'), ['4', '5', '6', '7', '8', '9'])
            ->get()
            ->keyBy(fn($item) => trim($item->account_code));

        $fastData = [];
        foreach ($data as $item) {
            $fastData[$item->account_code][$item->position] = (float) $item->total;
        }

        $result = collect();
        foreach ($fastData as $code => $positions) {
            $prefix = substr($code, 0, 1);
            $masterAcc = $masterAccounts->get($code);

            $groupInfo = AccountClassifier::determineGroup($prefix, $masterAcc->normal_balance ?? null);
            $isKredit  = $groupInfo['isKredit'];

            $debet  = $positions['DEBET'] ?? 0;
            $kredit = $positions['KREDIT'] ?? 0;

            $balance = $isKredit ? ($kredit - $debet) : ($debet - $kredit);

            $result->put($code, [
                'account_name' => $masterAcc->account_name ?? 'Akun Tidak Dikenal (Belum Didaftarkan)',
                'amount'       => abs(round($balance, 2)),
            ]);
        }

        return $result;
    }
}

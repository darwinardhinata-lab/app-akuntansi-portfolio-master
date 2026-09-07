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

    /**
     * Peta digit awal kode akun ERP -> label section native Jubelio yang setara.
     * Dipakai untuk membedakan akun-akun yang NAMANYA SAMA PERSIS tapi
     * sebenarnya akun berbeda di section berbeda (mis. "Toko Pakel +" di
     * Pendapatan Lainnya vs "Toko Pakel -" di Biaya; atau "Penyesuaian
     * Persediaan Barang" yang muncul baik di kode 77004 maupun 88004).
     */
    protected array $prefixToNativeSection = [
        '4' => 'pendapatan',
        '5' => 'harga pokok penjualan',
        '6' => 'biaya',
        '7' => 'pendapatan lainnya',
        '8' => 'biaya lainnya',
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
        $jubItems = $parsed['items']; // item_key (section||nama) => ['original_name','amount','section','normalized_name']

        $erpByCode = $this->getErpBalances($startDate, $endDate); // code => ['account_name','amount']
        $erpByKey = collect();
        foreach ($erpByCode as $code => $d) {
            $norm = $this->normalizeName($d['account_name']);
            $sectionLabel = $this->prefixToNativeSection[substr($code, 0, 1)] ?? null;
            $key = $this->buildItemKey($sectionLabel, $norm);
            $erpByKey->put($key, [
                'account_code'    => $code,
                'account_name'    => $d['account_name'],
                'amount'          => $d['amount'],
                'normalized_name' => $norm,
            ]);
        }

        $matchedErpKeys = [];
        $rows = [];
        $totalAbsSelisih = 0.0;
        $matchCount = 0;
        $mismatchCount = 0;
        $missingInErpCount = 0;
        $missingInJubCount = 0;
        $fuzzyCount = 0;

        foreach ($jubItems as $itemKey => $jub) {
            $erp = $erpByKey->get($itemKey);
            $matchType = 'EXACT';

            if (!$erp) {
                // Fallback: cocokkan berdasarkan kemiripan NAMA saja (lintas section),
                // untuk menangkap kasus typo/ejaan beda antara Jubelio & Master COA ERP.
                [$erp, $bestKey] = $this->fuzzyFindErpMatch($jub['normalized_name'], $erpByKey, $matchedErpKeys);
                if ($erp) {
                    $matchType = 'FUZZY';
                    $fuzzyCount++;
                    $matchedErpKeys[] = $bestKey;
                }
            } else {
                $matchedErpKeys[] = $itemKey;
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
                'account_name' => $jub['original_name'] . ($jub['section'] ? " ({$jub['section']})" : ''),
                'jubelio'      => round($jubAmount, 2),
                'erp'          => round($erpAmount, 2),
                'selisih'      => $selisih,
                'abs_selisih'  => $absSelisih,
                'status'       => $status,
                'match_type'   => $matchType,
            ];
        }

        foreach ($erpByKey as $key => $erp) {
            if (in_array($key, $matchedErpKeys, true)) {
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

    /**
     * Parse export/print native Jubelio.
     *
     * Format asli yang diamati (CSV, koma sbg delimiter, nominal di-quote
     * karena mengandung koma desimal):
     *
     *   textBox2,textBox1                              <- header generik, dilewati
     *   ,Pendapatan                                     <- baris section (kolom nominal kosong)
     *   "2.691.835.767,99",Penjualan                    <- item: NOMINAL DULU, baru nama
     *   "2.241.838.252,73",Total Pendapatan             <- subtotal, dilewati dari daftar item
     *
     * Kolom mana yang berisi nominal vs nama TIDAK diasumsikan tetap posisinya
     * (amount-first di sini, tapi kalau suatu saat Jubelio ubah urutan jadi
     * name-first, parser tetap benar) — dideteksi dari isinya: kolom yang match
     * pola angka Indonesia (mis. "-382.951.316,37") adalah nominal, sisanya nama.
     */
    public function parseJubelioNativeExport(UploadedFile $file): array
    {
        $items = collect();
        $controlTotals = [];
        $sectionSums = [];        // jumlah nilai ABSOLUT per section (dipakai utk info tampilan saja)
        $sectionSignedSums = [];  // jumlah nilai BERTANDA per section (dipakai utk kontrol silang ke subtotal tercetak)
        $currentSection = null;
        $skipped = 0;

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return ['items' => $items, 'control_totals' => [], 'section_sums' => [], 'section_signed_sums' => [], 'skipped_rows' => 0];
        }

        $isFirstRow = true;
        $amountPattern = '/^-?[\d.]+,\d{2}$/';

        while (($rawCols = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $cols = array_map(fn($c) => trim((string) $c), $rawCols);
            $cols = array_values(array_filter($cols, fn($c) => $c !== ''));

            if (empty($cols)) {
                continue;
            }

            // Lewati baris header generik (mis. "textBox2,textBox1") — tidak ada
            // satu pun kolom yang berbentuk nominal.
            if ($isFirstRow) {
                $isFirstRow = false;
                $hasNumeric = false;
                foreach ($cols as $c) {
                    if (preg_match($amountPattern, $c)) {
                        $hasNumeric = true;
                        break;
                    }
                }
                if (!$hasNumeric) {
                    continue;
                }
            }

            // Baris section: cuma tersisa 1 kolom (kolom nominal kosong), isinya
            // cocok salah satu judul section yang dikenal.
            if (count($cols) === 1) {
                $normLine = mb_strtolower($cols[0]);
                if (in_array($normLine, $this->nativeSectionHeaders, true)) {
                    $currentSection = $cols[0];
                    $sectionSums[$currentSection] = $sectionSums[$currentSection] ?? 0.0;
                    $sectionSignedSums[$currentSection] = $sectionSignedSums[$currentSection] ?? 0.0;
                } else {
                    $skipped++;
                }
                continue;
            }

            // Deteksi kolom nominal vs nama dari isinya, bukan dari posisi.
            $amountStr = null;
            $name = null;
            foreach ($cols as $c) {
                if (is_null($amountStr) && preg_match($amountPattern, $c)) {
                    $amountStr = $c;
                } elseif (is_null($name)) {
                    $name = $c;
                }
            }

            if (is_null($amountStr) || is_null($name)) {
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
            $itemKey = $this->buildItemKey($currentSection, $normalized);

            if ($items->has($itemKey)) {
                $existing = $items->get($itemKey);
                $existing['amount'] += abs($amount);
                $items->put($itemKey, $existing);
            } else {
                $items->put($itemKey, [
                    'original_name'  => $name,
                    'amount'         => abs($amount),
                    'section'        => $currentSection,
                    'normalized_name' => $normalized,
                ]);
            }

            if ($currentSection) {
                $sectionSums[$currentSection] = ($sectionSums[$currentSection] ?? 0.0) + abs($amount);
                $sectionSignedSums[$currentSection] = ($sectionSignedSums[$currentSection] ?? 0.0) + $amount;
            }
        }

        fclose($handle);

        return [
            'items'              => $items,
            'control_totals'     => $controlTotals,
            'section_sums'       => $sectionSums,
            'section_signed_sums' => $sectionSignedSums,
            'skipped_rows'       => $skipped,
        ];
    }

    /**
     * Bandingkan jumlah BERTANDA (bukan absolut) hasil parsing per section dengan
     * baris "Total ..." yang tercetak di file, sebagai deteksi dini kalau ada
     * baris yang gagal terbaca. Dipakai nilai bertanda karena subtotal di laporan
     * Jubelio adalah angka net (kontra-akun seperti "Diskon Penjualan" tercatat
     * negatif di file), bukan jumlah absolut.
     *
     * "Total Biaya" di layout Jubelio mencakup GABUNGAN dua section: "Biaya" dan
     * "Biaya Lainnya" — bukan section "Biaya" saja — makanya ditangani khusus.
     */
    protected function buildControlWarnings(array $parsed): array
    {
        $warnings = [];
        $signed = $parsed['section_signed_sums'] ?? [];

        $getSigned = function (string $sectionName) use ($signed) {
            foreach ($signed as $name => $sum) {
                if (mb_strtolower($name) === $sectionName) {
                    return $sum;
                }
            }
            return null;
        };

        $checks = [
            ['sections' => ['pendapatan'], 'control_key' => 'Total Pendapatan'],
            ['sections' => ['harga pokok penjualan'], 'control_key' => 'Total HPP'],
            ['sections' => ['biaya', 'biaya lainnya'], 'control_key' => 'Total Biaya'],
        ];

        foreach ($checks as $check) {
            if (!isset($parsed['control_totals'][$check['control_key']])) {
                continue;
            }

            $sum = 0.0;
            $anyFound = false;
            foreach ($check['sections'] as $sectionName) {
                $val = $getSigned($sectionName);
                if (!is_null($val)) {
                    $sum += $val;
                    $anyFound = true;
                }
            }
            if (!$anyFound) {
                continue;
            }

            $target = $parsed['control_totals'][$check['control_key']];
            $diff = round($sum - $target, 2);

            if (abs($diff) > 10) {
                $warnings[] = sprintf(
                    "Bagian '%s': jumlah baris yang terbaca (Rp %s) tidak sama dengan total tercetak di file (Rp %s), selisih Rp %s — kemungkinan ada baris yang gagal di-parse.",
                    implode(' + ', $check['sections']),
                    number_format($sum, 2, ',', '.'),
                    number_format($target, 2, ',', '.'),
                    number_format($diff, 2, ',', '.')
                );
            }
        }

        return $warnings;
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);
        // CATATAN: tanda "+"/"-" di akhir SENGAJA TIDAK dibuang di sini —
        // itu bagian dari identitas akun (akun "Toko X +" dan "Toko X -"
        // adalah dua akun GL yang berbeda, bukan sekadar variasi penulisan
        // akun yang sama). Lihat buildItemKey() untuk kunci pencocokan penuh.
        return trim($name);
    }

    /**
     * Kunci pencocokan akun = section + nama (dinormalisasi), BUKAN nama saja.
     * Ini wajib karena Jubelio/ERP bisa punya akun dengan nama identik di
     * section berbeda (mis. "Penyesuaian Persediaan Barang" ada di section
     * Pendapatan Lainnya kode 77004 DAN di section Biaya Lainnya kode 88004
     * — dua akun berbeda yang kebetulan namanya sama).
     */
    protected function buildItemKey(?string $section, string $normalizedName): string
    {
        $sectionKey = $section ? mb_strtolower(trim($section)) : '(tanpa-section)';
        return $sectionKey . '||' . $normalizedName;
    }

    protected function fuzzyFindErpMatch(string $normJub, Collection $erpByKey, array $alreadyMatchedKeys): array
    {
        $bestScore = 0.0;
        $bestKey = null;
        $bestData = null;

        foreach ($erpByKey as $key => $data) {
            if (in_array($key, $alreadyMatchedKeys, true)) {
                continue;
            }
            similar_text($normJub, $data['normalized_name'], $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $bestKey = $key;
                $bestData = $data;
            }
        }

        if ($bestData && $bestScore >= $this->fuzzyThreshold) {
            return [$bestData, $bestKey, $bestScore];
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
            ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
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

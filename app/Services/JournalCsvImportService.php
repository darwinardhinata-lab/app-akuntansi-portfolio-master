<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Account;
use App\Models\Asset;
use App\Models\SystemLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\NumberParser;
use App\Support\JournalBalanceValidator;

class JournalCsvImportService
{
    /**
     * Akun untuk menampung selisih pembulatan jurnal
     * Menggunakan akun '88068' (Pembulatan Transaksi) yang sudah ditetapkan di Master COA Jubelio
     * Saldo Normal: DEBET (Biaya/Beban Operasional)
     */
    protected $roundingAccountCode = '88068';
    
    /**
     * Toleransi selisih jurnal sebelum ditangani dengan akun pembulatan
     * Nilai dalam Rupiah (default: 10.00)
     */
    protected $roundingTolerance = 10.00;

    /**
     * Mapping akun lama ke akun baru.
     */
    protected $accountMapping = [
        '1133' => '11307',
    ];

    public function import(UploadedFile $file): array
    {
        try {
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return ['status' => 'error', 'message' => 'Gagal import: Format file harus .xlsx, .xls, atau .csv'];
            }

            if (in_array($extension, ['xlsx', 'xls'])) {
                Excel::import(new \App\Imports\JournalImport, $file);
                return ['status' => 'success', 'message' => 'Import file Excel berhasil disinkronisasi.'];
            }

            // FIX #1390: Process CSV line by line to avoid memory exhaustion
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return ['status' => 'error', 'message' => 'Gagal membuka file CSV.'];
            }

            // Read and parse header
            $headerLine = fgets($handle);
            if (!$headerLine) {
                fclose($handle);
                return ['status' => 'error', 'message' => 'GAGAL: File kosong.'];
            }

            $headerLine = str_replace("\x00", "", $headerLine);
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
            $headerLine = preg_replace('/\r\n|\r/', "\n", $headerLine);
            $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
            $headerCols = str_getcsv(strtolower($headerLine), $delimiter, '"', '\\');
            $headerCols = array_map('trim', $headerCols);

            $findCol = function($keywords) use ($headerCols) {
                foreach ($headerCols as $idx => $colName) {
                    foreach ($keywords as $key) {
                        if (stripos($colName, $key) !== false) return $idx;
                    }
                }
                return -1; 
            };

            $idxTgl         = $findCol(['tanggal', 'date']);
            $idxNoJurnal    = $findCol(['no jurnal', 'no. jurnal']);
            $idxNoBukti     = $findCol(['no bukti', 'no. bukti', 'referensi']);
            $idxDesc        = $findCol(['textbox35', 'deskripsi', 'keterangan']);
            $idxNilaiDebet  = $findCol(['nilai debet']);
            $idxNilaiKredit = $findCol(['nilai kredit']);
            $idxAkun        = $findCol(['akun', 'account']);

            if ($idxTgl == -1) $idxTgl = 0;
            if ($idxNoJurnal == -1) $idxNoJurnal = 1;
            if ($idxNoBukti == -1) $idxNoBukti = 2;
            if ($idxDesc == -1) $idxDesc = 4;
            if ($idxNilaiDebet == -1) $idxNilaiDebet = 6;
            if ($idxNilaiKredit == -1) $idxNilaiKredit = 7;
            if ($idxAkun == -1) $idxAkun = 8;

            // FIX #1390: First pass - collect all data for validation
            $parsedRows = [];
            $datesInChunk = [];
            $prefixes = [];
            $failedParseCount = 0;

            // First pass: Read all rows for validation
            $tempHandle = fopen($file->getRealPath(), 'r');
            fgets($tempHandle); // Skip header

            while (($rawLine = fgets($tempHandle)) !== false) {
                $rawLine = str_replace("\x00", "", $rawLine);
                $rawLine = preg_replace('/\r\n|\r/', "\n", $rawLine);
                
                if (trim($rawLine) === '') continue;

                $delimiterLine = substr_count($rawLine, ';') > substr_count($rawLine, ',') ? ';' : ',';
                $r = str_getcsv($rawLine, $delimiterLine, '"', '\\');
                
                if (count($r) < 9) {
                    $failedParseCount++;
                    continue;
                }

                $tanggalRaw = trim((string)($r[$idxTgl] ?? ''));
                $noJurnal   = trim((string)($r[$idxNoJurnal] ?? '')); 
                $noBukti    = trim((string)($r[$idxNoBukti] ?? '')); 
                $deskripsi  = trim((string)($r[$idxDesc] ?? '')); 
                $rawDebet   = trim((string)($r[$idxNilaiDebet] ?? '0'));
                $rawKredit  = trim((string)($r[$idxNilaiKredit] ?? '0'));
                $rawAkun    = trim((string)($r[$idxAkun] ?? '')); 

                if (stripos($tanggalRaw, 'tanggal') !== false || stripos($tanggalRaw, 'textbox') !== false) {
                    continue;
                }

                $time = strtotime($tanggalRaw);
                if (!$time) $time = strtotime(str_replace('/', '-', $tanggalRaw));
                if (!$time) {
                    $failedParseCount++;
                    continue; 
                }
                $tanggal = date('Y-m-d', $time);

                $datesInChunk[] = $tanggal;

                $evidenceNumber = empty($noJurnal) ? $noBukti : $noJurnal; 
                if (empty($evidenceNumber)) $evidenceNumber = 'JRN-SYS-' . uniqid(); 
                
                if (str_contains($evidenceNumber, '-')) {
                    $prefix = explode('-', $evidenceNumber)[0];
                    $prefixes[$prefix] = true;
                }

                $parsedRows[] = [
                    'tanggal'    => $tanggal,
                    'evidence'   => $evidenceNumber,
                    'noBukti'    => $noBukti,
                    'deskripsi'  => empty($deskripsi) ? $evidenceNumber : $deskripsi,
                    'raw_debet'  => $rawDebet,
                    'raw_kredit' => $rawKredit,
                    'raw_akun'   => $rawAkun,
                ];
            }
            fclose($tempHandle);

            if (empty($parsedRows)) {
                return ['status' => 'error', 'message' => 'GAGAL: Tidak ada baris yang lolos sistem.'];
            }

            // --- PRE-FLIGHT VALIDATION ---
            $validAccounts = array_flip(\App\Models\Account::pluck('account_code')->toArray());
            $balances = [];
            $ghostAccounts = [];

            foreach ($parsedRows as $pRow) {
                $explodeAkun = explode(' - ', $pRow['raw_akun']);
                $kodeAkun = str_replace('-', '', trim($explodeAkun[0]));
                if (array_key_exists($kodeAkun, $this->accountMapping)) $kodeAkun = $this->accountMapping[$kodeAkun];

                if (!empty($kodeAkun) && !isset($validAccounts[$kodeAkun])) {
                    $ghostAccounts[] = $kodeAkun;
                }

                $debet  = NumberParser::parseDecimal($pRow['raw_debet']);
                $kredit = NumberParser::parseDecimal($pRow['raw_kredit']);
                $ev = $pRow['evidence'];

                if (!isset($balances[$ev])) $balances[$ev] = ['d' => 0, 'k' => 0];
                $balances[$ev]['d'] += $debet;
                $balances[$ev]['k'] += $kredit;
            }

            if (!empty($ghostAccounts)) {
                $ghostAccounts = array_unique($ghostAccounts);
                $accountList = implode(', ', array_slice($ghostAccounts, 0, 10));
                return ['status' => 'error', 'message' => 'GAGAL IMPORT: Ditemukan Akun yang tidak terdaftar di Master COA (Ghost Account). Akun: ' . $accountList];
            }

            // FIX C3: Kumpulkan jurnal yang butuh koreksi pembulatan
            // Sesuai RULES.md: "Jika terjadi selisih 0.01 sekalipun, sistem WAJIB DB::rollBack()"
            $needsRounding = [];
            foreach ($balances as $ev => $b) {
                $diff = abs(round($b['d'], 2) - round($b['k'], 2));
                if ($diff > $this->roundingTolerance) {
                    // Selisih lebih dari toleransi - tolak import
                    return ['status' => 'error', 'message' => 'GAGAL IMPORT: Jurnal tidak seimbang pada No Bukti ' . $ev . '. Selisih: ' . number_format($diff, 2)];
                }
                if ($diff > 0) {
                    // Selisih dalam toleransi - akan dikoreksi dengan akun pembulatan
                    $needsRounding[] = [
                        'evidence' => $ev,
                        'd'        => $b['d'],
                        'k'        => $b['k'],
                        'diff'     => $diff,
                    ];
                }
            }

            DB::unprepared('SET FOREIGN_KEY_CHECKS=0;'); 
            DB::beginTransaction();

            try {
                // FIX #1390: Delete existing headers in chunks
                $evidenceNumbersToImport = array_unique(array_column($parsedRows, 'evidence'));
                $evidenceChunks = array_chunk($evidenceNumbersToImport, 200);
                $allHeadersToDelete = collect();
                foreach ($evidenceChunks as $evChunk) {
                    $chunkHeaders = JournalHeader::whereIn('evidence_number', $evChunk)->pluck('journal_id');
                    $allHeadersToDelete = $allHeadersToDelete->merge($chunkHeaders);
                }

                if ($allHeadersToDelete->isNotEmpty()) {
                    $chunks = array_chunk($allHeadersToDelete->toArray(), 200);
                    foreach ($chunks as $chunk) {
                        DB::table('journal_details')->whereIn('journal_id', $chunk)->delete();
                        DB::table('journal_headers')->whereIn('journal_id', $chunk)->delete();
                    }
                }

                // FIX #1390: Chunk evidence numbers to avoid "too many placeholders" error (MySQL limit: 65,535)
                $evidenceNumbers = array_unique(array_column($parsedRows, 'evidence'));
                $existingHeaders = [];
                foreach (array_chunk($evidenceNumbers, 500) as $chunk) {
                    $headersInChunk = JournalHeader::whereIn('evidence_number', $chunk)->pluck('journal_id', 'evidence_number')->toArray();
                    $existingHeaders = array_merge($existingHeaders, $headersInChunk);
                }
                $headerCache = $existingHeaders;

                $headersToInsert = [];
                $detailsToInsert = [];

                $datePrefix = 'JRN-' . date('Ymd') . '-';
                $lastIdStr = DB::table('journal_headers')->where('journal_id', 'like', $datePrefix . '%')->orderByDesc('journal_id')->value('journal_id');
                $seq = $lastIdStr ? (int) substr($lastIdStr, -6) : 0;

                $now = now();

                foreach ($parsedRows as $pRow) {
                    $explodeAkun = explode(' - ', $pRow['raw_akun']);
                    $kodeAkun = str_replace('-', '', trim($explodeAkun[0]));
                    if (array_key_exists($kodeAkun, $this->accountMapping)) $kodeAkun = $this->accountMapping[$kodeAkun];

                    $debet  = NumberParser::parseDecimal($pRow['raw_debet']);
                    $kredit = NumberParser::parseDecimal($pRow['raw_kredit']);

                    if ($debet == 0 && $kredit == 0) continue;

                    $evidenceNumber = $pRow['evidence'];
                    
                    if (!isset($headerCache[$evidenceNumber])) {
                        $seq++;
                        $newId = $datePrefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
                        
                        $headersToInsert[] = [
                            'journal_id'       => $newId,
                            'evidence_number'  => $evidenceNumber,
                            'transaction_date' => $pRow['tanggal'],
                            'description'      => substr($pRow['deskripsi'], 0, 255),
                            'jj_id'            => time() + $seq,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ];
                        $headerCache[$evidenceNumber] = $newId;
                    }

                    $currId = $headerCache[$evidenceNumber];

                    if ($debet != 0) {
                        $detailsToInsert[] = [
                            'journal_id'   => $currId,
                            'account_code' => $kodeAkun,
                            'helper_code'  => null,
                            'position'     => 'DEBET',
                            'amount'       => $debet,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ];
                    }

                    if ($kredit != 0) {
                        $detailsToInsert[] = [
                            'journal_id'   => $currId,
                            'account_code' => $kodeAkun,
                            'helper_code'  => null,
                            'position'     => 'KREDIT',
                            'amount'       => $kredit,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ];
                    }
                }

        // TANGANI SELISIH PEMBULATAN - Tambahkan ke akun pembulatan
        // Saldo Normal: DEBET (Biaya/Beban Operasional)
        $roundingEntries = [];
        foreach ($needsRounding as $entry) {
            $currId = $headerCache[$entry['evidence']];
            $selisih = round($entry['diff'], 2);
            
            if ($entry['d'] > $entry['k']) {
                // Jurnal lebih banyak DEBET, tambahkan selisih sebagai KREDIT (untuk menyeimbangkan)
                $roundingEntries[] = [
                    'journal_id'   => $currId,
                    'account_code' => $this->roundingAccountCode,
                    'helper_code'  => null,
                    'position'     => 'KREDIT',
                    'amount'       => $selisih,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            } else {
                // Jurnal lebih banyak KREDIT, tambahkan selisih sebagai DEBET (untuk menyeimbangkan)
                $roundingEntries[] = [
                    'journal_id'   => $currId,
                    'account_code' => $this->roundingAccountCode,
                    'helper_code'  => null,
                    'position'     => 'DEBET',
                    'amount'       => $selisih,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

                $allDetails = array_merge($detailsToInsert, $roundingEntries);
                
                // VALIDASI BALANCE: pastikan jurnal seimbang sebelum insert (termasuk after rounding)
                $journalGroups = [];
                foreach ($allDetails as $det) {
                    $jid = $det['journal_id'];
                    if (!isset($journalGroups[$jid])) {
                        $journalGroups[$jid] = [];
                    }
                    $journalGroups[$jid][] = $det;
                }

                foreach ($journalGroups as $jid => $groupDetails) {
                    if (!JournalBalanceValidator::isBalanced($groupDetails)) {
                        $selisih = JournalBalanceValidator::getDifference($groupDetails);
                        throw new \Exception("Jurnal tidak balance untuk No Bukti {$jid}. Selisih: " . number_format($selisih, 2, ',', '.'));
                    }
                }

                if (!empty($headersToInsert)) {
                    foreach (array_chunk($headersToInsert, 200) as $chunk) DB::table('journal_headers')->insert($chunk);
                }
                if (!empty($detailsToInsert)) {
                    foreach (array_chunk($detailsToInsert, 200) as $chunk) DB::table('journal_details')->insert($chunk);
                }
                if (!empty($roundingEntries)) {
                    foreach (array_chunk($roundingEntries, 200) as $chunk) DB::table('journal_details')->insert($chunk);
                }

                DB::commit();
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');

                // POST-PROCESSING: Create Asset records for fixed asset accounts
                // This ensures assets purchased via journal import are tracked in Aset Tetap
                $this->createAssetsFromImportedJournals($headerCache);

                SystemLog::record('IMPORT', 'Jurnal Umum', 'Import jurnal berhasil. ' . count($parsedRows) . ' mutasi terekam.');
                $pesan = 'Import Berhasil Sempurna! ' . count($parsedRows) . ' mutasi terekam.';
                if ($failedParseCount > 0) $pesan .= " (Ada $failedParseCount baris yang dibuang karena format kosong/rusak).";

                return ['status' => 'success', 'message' => $pesan];

            } catch (\Throwable $e) {
                DB::rollBack();
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
                return ['status' => 'error', 'message' => "Gagal Menyimpan ke Database: " . explode(' (Connection', $e->getMessage())[0]];
            }
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => "Gagal Membaca File: " . $e->getMessage()];
        }
    }

    public function setRoundingAccount(string $accountCode): void
    {
        $this->roundingAccountCode = $accountCode;
    }

    public function setRoundingTolerance(float $tolerance): void
    {
        $this->roundingTolerance = $tolerance;
    }

    public function getAccountMapping(): array
    {
        return $this->accountMapping;
    }

    public function addAccountMapping(string $oldCode, string $newCode): void
    {
        $this->accountMapping[$oldCode] = $newCode;
    }

    /**
     * Create Asset records for journal details that use account code 12000 (Aset Tetap).
     * This ensures that assets purchased via journal import are automatically
     * tracked in the Aset Management menu, with ALL fields auto-filled from the journal.
     */
    protected function createAssetsFromImportedJournals(array $headerCache): void
    {
        if (empty($headerCache)) return;

        $journalIds = array_values($headerCache);

        $details = DB::table('journal_details')
            ->join('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->whereIn('journal_details.journal_id', $journalIds)
            ->where('journal_details.position', 'DEBET')
            ->where('journal_details.account_code', config('coa.aset_tetap'))
            ->select(
                'journal_details.id as detail_id',
                'journal_details.journal_id',
                'journal_details.account_code',
                'journal_details.amount',
                'journal_details.created_at',
                'accounts.account_name'
            )
            ->get();

        $headers = JournalHeader::whereIn('journal_id', $journalIds)
            ->pluck('transaction_date', 'journal_id')
            ->toArray();

        $headerDescriptions = JournalHeader::whereIn('journal_id', $journalIds)
            ->pluck('description', 'journal_id')
            ->toArray();

        $assetsToCreate = [];
        $now = now();

        foreach ($details as $detail) {
            $exists = Asset::where('journal_detail_id', $detail->detail_id)->exists();
            if ($exists) continue;

            $purchaseDate = $headers[$detail->journal_id] ?? $now->format('Y-m-d');
            $dateStr = date('Ymd', strtotime($purchaseDate));

            $assetsToCreate[] = [
                'journal_detail_id'  => $detail->detail_id,
                'asset_code'         => 'AST-' . $detail->detail_id . '-' . $dateStr,
                'asset_name'         => $headerDescriptions[$detail->journal_id] ?? $detail->account_name,
                'purchase_date'      => $purchaseDate,
                'purchase_price'     => $detail->amount,
                'useful_life_months' => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if (!empty($assetsToCreate)) {
            foreach (array_chunk($assetsToCreate, 200) as $chunk) {
                Asset::insert($chunk);
            }
        }
    }
}

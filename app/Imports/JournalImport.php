<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Account;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\NumberParser;

class JournalImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    private $delimiter = ','; 
    
    /**
     * Akun untuk menampung selisih pembulatan jurnal
     * Menggunakan akun '88068' (Pembulatan Transaksi) yang sudah ditetapkan di Master COA Jubelio
     * Saldo Normal: DEBET (Biaya/Beban Operasional)
     * Untuk pembulatan: selisih DEBET → tambah DEBET, selisih KREDIT → tambah KREDIT
     */
    protected $roundingAccountCode = '88068'; // Default, akan di-override di constructor
    
    /**
     * Toleransi selisih jurnal sebelum ditangani dengan akun pembulatan
     * Nilai dalam Rupiah (default: 10.00)
     */
    protected $roundingTolerance = 10.00;

    /**
     * Prefix nomor dokumen otomatis sistem yang TIDAK BOLEH ditimpa
     * oleh Import Jurnal Umum (karena di-generate oleh SalesOrderService/PurchaseOrderService
     * dengan breakdown akun yang presisi: piutang, diskon, HPP, pajak, dll)
     */
    protected $protectedPrefixes = ['INV-', 'BIL-'];

    public function __construct($filePath = null)
    {
        // P1-2: Gunakan konfigurasi COA terpusat untuk akun pembulatan
        $this->roundingAccountCode = config('coa.pembulatan', '88068');

        if ($filePath && file_exists($filePath)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                fclose($handle);
                if ($firstLine && str_contains($firstLine, ';')) {
                    $this->delimiter = ';';
                }
            }
        }
    }

    public function startRow(): int { return 2; }
    
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter
        ];
    }
    
    public function collection(Collection $rows)
    {
        $now = now();
        $accountMapping = ['1133' => '11307'];

        // --- PRE-PREPARATION FOR CHUNK ---
        $parsedRows = [];
        $datesInChunk = [];
        $prefixes = [];
        $rawNoBuktiList = [];

        foreach ($rows as $row) {
            $r = is_object($row) ? $row->toArray() : (is_array($row) ? $row : []);
            if (count($r) < 9) continue;

            $tanggal   = trim((string)$r[0]);
            $noJurnal  = trim((string)$r[1]); 
            $noBukti   = trim((string)$r[2]); 

            if (empty($tanggal) || strtolower($tanggal) === 'tanggal') continue;

            if (is_numeric($tanggal) && strlen($tanggal) <= 5) {
                $tanggal = date('Y-m-d', Date::excelToTimestamp($tanggal));
            } else {
                $tanggal = date('Y-m-d', strtotime(str_replace('/', '-', $tanggal)));
            }

            $datesInChunk[] = $tanggal;

            $evidenceNumber = empty($noJurnal) ? $noBukti : $noJurnal; 
            if (!empty($evidenceNumber) && str_contains($evidenceNumber, '-')) {
                $prefix = explode('-', $evidenceNumber)[0];
                $prefixes[$prefix] = true;
            }

            // FIX: DRY — gunakan NumberParser helper
            $parsedNominal = [
                NumberParser::parseDecimal((string)$r[6]),
                NumberParser::parseDecimal((string)$r[7]),
            ];

            $parsedRows[] = [
                'tanggal'    => $tanggal,
                'evidence'   => $evidenceNumber,
                'no_bukti'   => $noBukti, // simpan No Bukti mentah untuk source_doc_no + cross-check
                'deskripsi'  => trim((string)$r[3]),
                'debet'      => $parsedNominal[0],
                'kredit'     => $parsedNominal[1],
                'raw_akun'   => trim((string)$r[8]),
            ];

            if (!empty($noBukti)) {
                $rawNoBuktiList[] = $noBukti;
            }
        }

        if (empty($parsedRows)) return;

        // --- GUARD 1: Cegah import CSV Jurnal Umum menimpa jurnal otomatis sistem via evidence_number ---
        $evidenceNumbersToImport = array_unique(array_column($parsedRows, 'evidence'));
        foreach ($evidenceNumbersToImport as $ev) {
            foreach ($this->protectedPrefixes as $pfx) {
                if (str_starts_with($ev, $pfx)) {
                    throw new \Exception(
                        "Gagal Import: '{$ev}' adalah nomor dokumen otomatis sistem (SO/PO/Invoice). "
                        . "Import Jurnal Umum tidak boleh menimpa jurnal transaksional. "
                        . "Gunakan menu koreksi jurnal manual jika perlu adjustment."
                    );
                }
            }
        }

        // --- GUARD 2: Cross-check raw No Bukti (INV-/BIL-) yang mungkin "tersembunyi" walau No Jurnal terisi ---
        // Skenario: No Jurnal = 1399694 (Jubelio internal), No Bukti = INV-260729-0001
        // evidence_number = 1399694 (tidak terdeteksi Guard 1), tapi No Bukti berisi INV- yang sudah punya jurnal sistem
        $rawNoBuktiList = array_unique(array_filter($rawNoBuktiList));
        if (!empty($rawNoBuktiList)) {
            $docRefs = [];
            foreach ($rawNoBuktiList as $nb) {
                $prefix = strtoupper(explode('-', $nb)[0] ?? '');
                if (in_array($prefix, ['INV', 'BIL'])) {
                    $docRefs[] = $nb;
                }
            }
            if (!empty($docRefs)) {
                $existingInvoices = \App\Models\SalesInvoice::whereIn('invoice_number', $docRefs)
                    ->whereNotNull('journal_id')
                    ->pluck('invoice_number');
                $existingBills = \App\Models\PurchaseBill::whereIn('bill_number', $docRefs)
                    ->whereNotNull('journal_id')
                    ->pluck('bill_number');
                $blocked = $existingInvoices->merge($existingBills);
                if ($blocked->isNotEmpty()) {
                    throw new \Exception(
                        "Gagal Import: Dokumen berikut SUDAH punya jurnal otomatis dari sistem dan terdeteksi "
                        . "di kolom 'No Bukti' CSV Anda: {$blocked->implode(', ')}. "
                        . "Ini berisiko dobel posting. Hapus baris tersebut dari CSV, atau hapus dulu jurnal "
                        . "sistemnya jika memang ingin diganti manual."
                    );
                }
            }
        }

        // --- PRE-FLIGHT DOUBLE ENTRY VALIDATION ---
        $balances = [];
        foreach ($parsedRows as $pRow) {
            $ev = $pRow['evidence'];
            if (!isset($balances[$ev])) {
                $balances[$ev] = ['debet' => 0, 'kredit' => 0];
            }
            $balances[$ev]['debet'] += $pRow['debet'];
            $balances[$ev]['kredit'] += $pRow['kredit'];
        }

        // TANGGUH IMBALANCE DENGAN AKUN SELISIH PEMBULATAN (ROUNDING)
        $needsRounding = [];
        foreach ($balances as $ev => $bal) {
            $diff = abs(round($bal['debet'], 2) - round($bal['kredit'], 2));
            if ($diff > $this->roundingTolerance) {
                throw new \Exception("Gagal Import: Jurnal tidak seimbang pada No Bukti {$ev}. (Debet: " . number_format($bal['debet'], 2) . ", Kredit: " . number_format($bal['kredit'], 2) . "). Selisih: " . number_format($diff, 2));
            }
            if ($diff > 0) {
                $needsRounding[] = [
                    'evidence' => $ev,
                    'debet'    => $bal['debet'],
                    'kredit'   => $bal['kredit'],
                    'diff'     => $diff,
                ];
            }
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0;'); 
        DB::beginTransaction();

        try {
            // --- FASE AUTO-WIPE ---
            $minDate = min($datesInChunk);
            $maxDate = max($datesInChunk);
            $prefixList = array_keys($prefixes);

            // FIX #013: Add safety lock - only delete entries that match the EXACT evidence numbers being imported
            // This prevents accidental deletion of manually entered journals in the same date range
            // FIX #1390: Chunk evidence numbers to avoid "too many placeholders" error
            $evidenceNumbersToImport = array_unique(array_column($parsedRows, 'evidence'));
            
            // Only delete entries that match evidence numbers being re-imported
            // Chunk evidence numbers to avoid MySQL placeholder limit (max 65,535)
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

            // --- FASE INJEKSI DATA BARU TINGKAT LANJUT ---
            $headersToInsert = [];
            $detailsToInsert = [];
            $headerCache = [];

            // Membaca ID terakhir untuk Auto-Generate JRN-
            $datePrefix = 'JRN-' . date('Ymd') . '-';
            $lastIdStr = DB::table('journal_headers')->where('journal_id', 'like', $datePrefix . '%')->orderByDesc('journal_id')->value('journal_id');
            $seq = $lastIdStr ? (int) substr($lastIdStr, -6) : 0;

            // FIX #1390: Load header yang mungkin sudah tersimpan di database dari putaran sebelumnya
            // Chunk evidence numbers to avoid "too many placeholders" error (MySQL limit: 65,535)
            $evidenceNumbers = array_unique(array_column($parsedRows, 'evidence'));
            $existingHeaders = [];
            foreach (array_chunk($evidenceNumbers, 500) as $chunk) {
                $headersInChunk = JournalHeader::whereIn('evidence_number', $chunk)->pluck('journal_id', 'evidence_number')->toArray();
                $existingHeaders = array_merge($existingHeaders, $headersInChunk);
            }
            $headerCache = $existingHeaders;

            foreach ($parsedRows as $pRow) {
                $explodeAkun = explode(' - ', $pRow['raw_akun']);
                $kodeAkun = str_replace('-', '', trim($explodeAkun[0]));

                if (array_key_exists($kodeAkun, $accountMapping)) {
                    $kodeAkun = $accountMapping[$kodeAkun];
                }

                // FIX: DRY — gunakan NumberParser helper
                $debet  = NumberParser::parseDecimal($pRow['debet']);
                $kredit = NumberParser::parseDecimal($pRow['kredit']);

                if ($debet == 0 && $kredit == 0) continue;

                $evidenceNumber = $pRow['evidence'];
                
                // FIX: Pembuatan Header Otomatis Tanpa Model Create yang Lambat
                if (!isset($headerCache[$evidenceNumber])) {
                    $seq++;
                    $newId = $datePrefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
                    
                    $headersToInsert[] = [
                        'journal_id'       => $newId,
                        'evidence_number'  => $evidenceNumber,
                        'source_doc_no'    => $pRow['no_bukti'] ?: null, // FIX: traceability untuk DocumentTrace
                        'transaction_date' => $pRow['tanggal'],
                        'description'      => substr($evidenceNumber . ' - ' . $pRow['deskripsi'], 0, 255),
                        'jj_id'            => time() + $seq, // FIX 1364: Bypass constraint NOT NULL dari Jubelio
                        'created_at'       => now(),
                        'updated_at'       => now(),
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
                         'created_at'   => now(),
                         'updated_at'   => now(),
                     ];
                 }

                  if ($kredit != 0) {
                      $detailsToInsert[] = [
                          'journal_id'   => $currId,
                          'account_code' => $kodeAkun,
                          'helper_code'  => null,
                          'position'     => 'KREDIT',
                          'amount'       => $kredit,
                          'created_at'   => now(),
                          'updated_at'   => now(),
                      ];
                  }
            }

            // TANGANI SELISIH PEMBULATAN - Tambahkan ke akun 88068 (Pembulatan Transaksi)
            // Saldo Normal: DEBET (Biaya/Beban Operasional)
            $roundingEntries = [];
            foreach ($needsRounding as $entry) {
                $currId = $headerCache[$entry['evidence']];
                $selisih = round($entry['diff'], 2);
                
                if ($entry['debet'] > $entry['kredit']) {
                    // Jurnal lebih banyak KREDIT, tambahkan selisih sebagai DEBET
                    // (karena akun 88068 DEBET normal, DEBET akan menambah nilai positif)
                    $roundingEntries[] = [
                        'journal_id'   => $currId,
                        'account_code' => $this->roundingAccountCode,
                        'helper_code'  => null,
                        'position'     => 'DEBET',
                        'amount'       => $selisih,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                } else {
                    // Jurnal lebih banyak DEBET, tambahkan selisih sebagai KREDIT
                    // (karena akun 88068 DEBET normal, KREDIT akan mengurangi nilai positif)
                    $roundingEntries[] = [
                        'journal_id'   => $currId,
                        'account_code' => $this->roundingAccountCode,
                        'helper_code'  => null,
                        'position'     => 'KREDIT',
                        'amount'       => $selisih,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
            }

            if (!empty($headersToInsert)) {
                foreach (array_chunk($headersToInsert, 200) as $chunk) {
                    DB::table('journal_headers')->insert($chunk);
                }
            }
            if (!empty($detailsToInsert)) {
                foreach (array_chunk($detailsToInsert, 200) as $chunk) {
                    DB::table('journal_details')->insert($chunk);
                }
            }
            if (!empty($roundingEntries)) {
                foreach (array_chunk($roundingEntries, 200) as $chunk) {
                    DB::table('journal_details')->insert($chunk);
                }
            }

            // P1-3: Validasi final menggunakan JournalBalanceValidator terpusat
            // Pastikan setiap evidence_number yang di-import benar-benar seimbang setelah
            // penambahan akun pembulatan
            $allDetailsByEvidence = [];
            foreach ($parsedRows as $pRow) {
                $ev = $pRow['evidence'];
                if (!isset($allDetailsByEvidence[$ev])) {
                    $allDetailsByEvidence[$ev] = [];
                }
                if ($pRow['debet'] != 0) {
                    $allDetailsByEvidence[$ev][] = ['position' => 'DEBET', 'amount' => $pRow['debet']];
                }
                if ($pRow['kredit'] != 0) {
                    $allDetailsByEvidence[$ev][] = ['position' => 'KREDIT', 'amount' => $pRow['kredit']];
                }
            }
            // Tambahkan rounding entries ke dalam validasi
            foreach ($needsRounding as $entry) {
                $ev = $entry['evidence'];
                $selisih = round($entry['diff'], 2);
                if ($entry['debet'] > $entry['kredit']) {
                    $allDetailsByEvidence[$ev][] = ['position' => 'DEBET', 'amount' => $selisih];
                } else {
                    $allDetailsByEvidence[$ev][] = ['position' => 'KREDIT', 'amount' => $selisih];
                }
            }
            // Validasi setiap evidence number benar-benar balance
            foreach ($allDetailsByEvidence as $ev => $lines) {
                if (!\App\Support\JournalBalanceValidator::isBalanced($lines)) {
                    throw new \Exception("P1-3: Validasi final gagal — Jurnal tidak balance untuk No Bukti {$ev} setelah pembulatan.");
                }
            }

            DB::commit();
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');

            // POST-PROCESSING: Create Asset records for Aset Tetap
            $this->createAssetsFromImportedJournals($headerCache);

        } catch (\Exception $e) {
            DB::rollBack();
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
            throw $e; 
        }
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

        // Only detect account code from config (Aset Tetap)
        $details = DB::table('journal_details')
            ->whereIn('journal_details.journal_id', $journalIds)
            ->where('journal_details.position', 'DEBET')
            ->where('journal_details.account_code', config('coa.aset_tetap'))
            ->select(
                'journal_details.id as detail_id',
                'journal_details.journal_id',
                'journal_details.amount',
                'journal_details.created_at'
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
                'asset_name'         => $headerDescriptions[$detail->journal_id] ?? 'Aset Tetap',
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

    /**
     * Set akun untuk selisih pembulatan
     * @param string $accountCode
     */
    public function setRoundingAccount(string $accountCode): void
    {
        $this->roundingAccountCode = $accountCode;
    }
    
    /**
     * Set toleransi selisih
     * @param float $tolerance
     */
    public function setRoundingTolerance(float $tolerance): void
    {
        $this->roundingTolerance = $tolerance;
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\JournalHeader;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\NumberParser;

class FastImportJurnal extends Command
{
    protected $signature = 'jurnal:fast {file}';
    protected $description = 'Import Jurnal massal dari file CSV via Terminal (Ultra Fast)';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("❌ File tidak ditemukan di jalur: {$filePath}");
            return;
        }

        $handle = fopen($filePath, "r");
        if (!$handle) {
            $this->error("❌ Gagal membuka file CSV.");
            return;
        }

        fgets($handle);

        $detailsToInsert = [];
        $headersToInsert = []; // FIX: ARRAY PENAMPUNG HEADER BARU
        $count = 0;
        $now = now();
        
        $this->info("⚡ MEMULAI MODE ULTRA CEPAT (TRANSACTION RAM)");
        
        // FIX: MATIKAN BATAS MEMORI DAN QUERY LOG AGAR RAM TIDAK BOCOR
        ini_set('memory_limit', '1024M');
        DB::disableQueryLog();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->info("⏳ Memuat seluruh Nomor Bukti ke dalam Memori...");
        
        // OPTIMASI 1: Tarik SEMUA nomor bukti yang sudah ada sekaligus (Bebas Query berulang)
        $headers = DB::table('journal_headers')->select('evidence_number', 'journal_id')->get();
        $headerCache = [];
        foreach($headers as $h) {
            $headerCache[$h->evidence_number] = $h->journal_id;
        }

        // FIX: Hapus dulu evidence_number yang bakal di-re-import, SEBELUM insert baru
        // (Meniru pola aman FastSyncJurnal & JournalImport agar tidak menumpuk baris di journal_id yang sama)
        $this->info("🔍 Memindai evidence_number di file untuk cek duplikasi...");
        rewind($handle);
        fgets($handle);
        $evidenceNumbersInFile = [];
        $rawNoBuktiList = [];
        while (($rawLine = fgets($handle)) !== false) {
            if (trim($rawLine) === '') continue;
            $delimiter = str_contains($rawLine, ';') ? ';' : ',';
            $r = str_getcsv($rawLine, $delimiter, '"', '\\');
            if (count($r) < 9) continue;
            $noJurnal = trim((string)($r[1] ?? ''));
            $noBukti  = trim((string)($r[2] ?? ''));
            $ev = empty($noJurnal) ? $noBukti : $noJurnal;
            if (!empty($ev)) $evidenceNumbersInFile[$ev] = true;
            if (!empty($noBukti)) $rawNoBuktiList[] = $noBukti;
        }
        rewind($handle);
        fgets($handle);

        // GUARD 2: Cross-check raw No Bukti (INV-/BIL-) yang mungkin tersembunyi walau No Jurnal terisi
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
                    $this->error("❌ GAGAL: Dokumen berikut SUDAH punya jurnal otomatis dari sistem dan terdeteksi "
                        . "di kolom 'No Bukti' CSV Anda: {$blocked->implode(', ')}. "
                        . "Ini berisiko dobel posting. Hapus baris tersebut dari CSV, atau hapus dulu jurnal "
                        . "sistemnya jika memang ingin diganti manual.");
                    return;
                }
            }
        }

        $evList = array_keys($evidenceNumbersInFile);
        $deletedCount = 0;
        foreach (array_chunk($evList, 500) as $chunk) {
            $idsToDelete = DB::table('journal_headers')->whereIn('evidence_number', $chunk)->pluck('journal_id');
            if ($idsToDelete->isNotEmpty()) {
                DB::table('journal_details')->whereIn('journal_id', $idsToDelete)->delete();
                DB::table('journal_headers')->whereIn('journal_id', $idsToDelete)->delete();
                $deletedCount += $idsToDelete->count();
                // Refresh headerCache: hapus evidence_number yang sudah di-delete agar dianggap "baru"
                foreach ($chunk as $ev) {
                    unset($headerCache[$ev]);
                }
            }
        }
        if ($deletedCount > 0) {
            $this->info("🧹 {$deletedCount} data lama yang evidence_number-nya bentrok sudah dibersihkan.");
        } else {
            $this->info("✨ Tidak ada data lama yang perlu dibersihkan. Lanjut Mode Insert.");
        }

        // FIX: PRE-CALCULATE ID JURNAL TERAKHIR AGAR TIDAK QUERY BERULANG DI DALAM LOOP
        $datePrefix = 'JRN-' . date('Ymd') . '-';
        $lastIdStr = DB::table('journal_headers')
                        ->where('journal_id', 'like', $datePrefix . '%')
                        ->orderByDesc('journal_id')
                        ->value('journal_id');
        $seq = $lastIdStr ? (int) substr($lastIdStr, -6) : 0;

        $accountMapping = [
            '1133' => '11307',
        ];

        $this->info("🔄 Membaca dan menampung baris data (Tidak menulis ke disk dulu)...");

        // OPTIMASI 2: Kunci database ke RAM (Transaction)
        DB::beginTransaction();

        try {
            while (($rawLine = fgets($handle)) !== false) {
                if (trim($rawLine) === '') continue;

                $delimiter = str_contains($rawLine, ';') ? ';' : ',';
                $r = str_getcsv($rawLine, $delimiter, '"', '\\');
                
                if (count($r) < 9) continue;

                $tanggal   = trim((string)$r[0]);
                $noJurnal  = trim((string)$r[1]); 
                $noBukti   = trim((string)$r[2]); 
                $deskripsi = trim((string)$r[3]); 
                $rawDebet  = trim((string)$r[6]);
                $rawKredit = trim((string)$r[7]);
                $rawAkun   = trim((string)$r[8]); 

                if (empty($tanggal) || strtolower($tanggal) === 'tanggal') continue;

                // --- PARSER TANGGAL ---
                if (is_numeric($tanggal) && strlen($tanggal) <= 5) {
                    $tanggal = date('Y-m-d', Date::excelToTimestamp($tanggal));
                } else {
                    $tanggal = date('Y-m-d', strtotime(str_replace('/', '-', $tanggal)));
                }

                // --- PARSER KODE AKUN (FORMAT CSV IMPORT) ---
                $explodeAkun = explode(' - ', $rawAkun);
                $kodeAkunKotor = trim($explodeAkun[0]);
                $kodeAkun = str_replace('-', '', $kodeAkunKotor);

                if (array_key_exists($kodeAkun, $accountMapping)) {
                    $kodeAkun = $accountMapping[$kodeAkun];
                }

                // FIX: DRY — gunakan NumberParser helper
                $debet  = NumberParser::parseDecimal($rawDebet);
                $kredit = NumberParser::parseDecimal($rawKredit);

                if ($debet == 0 && $kredit == 0) continue; 

                // --- BUAT HEADER (Hanya jika benar-benar belum ada di Cache) ---
                $evidenceNumber = empty($noJurnal) ? $noBukti : $noJurnal; 
                if (empty($evidenceNumber)) continue;

                if (!isset($headerCache[$evidenceNumber])) {
                    // FIX: KALKULASI ID MANUAL DI RAM UNTUK MENCEGAH N+1 QUERY
                    $seq++;
                    $newId = $datePrefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
                    
                    $headersToInsert[] = [
                        'journal_id'       => $newId,
                        'evidence_number'  => $evidenceNumber,
                        'source_doc_no'    => $noBukti ?: null, // FIX: traceability untuk DocumentTrace + cross-check masa depan
                        'transaction_date' => $tanggal,
                        'description'      => $noBukti . ' - ' . $deskripsi,
                        'jj_id'            => time() + $seq, // FIX 1364
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];

                    $headerCache[$evidenceNumber] = $newId;
                }

                // --- TAMPUNG KE ARRAY DETAILS ---
                if ($debet != 0) {
                    $detailsToInsert[] = [
                        'journal_id'   => $headerCache[$evidenceNumber],
                        'account_code' => $kodeAkun,
                        'helper_code'  => null,
                        'position'     => 'DEBET',
                        'amount'       => $debet,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $count++;
                }

                if ($kredit != 0) {
                    $detailsToInsert[] = [
                        'journal_id'   => $headerCache[$evidenceNumber],
                        'account_code' => $kodeAkun,
                        'helper_code'  => null,
                        'position'     => 'KREDIT',
                        'amount'       => $kredit,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $count++;
                }

                // Kuras Array RAM setiap 5.000 baris agar laptop tidak nge-freeze
                if (count($detailsToInsert) >= 5000) {
                    // FIX #1390: INSERT HEADER TERLEBIH DAHULU KARENA DETAIL BERGANTUNG PADA FOREIGN KEY
                    // Chunk headers to avoid "too many placeholders" error (MySQL limit: 65,535)
                    if (count($headersToInsert) > 0) {
                        foreach (array_chunk($headersToInsert, 500) as $chunk) {
                            DB::table('journal_headers')->insert($chunk);
                        }
                        $headersToInsert = [];
                    }

                    DB::table('journal_details')->insert($detailsToInsert);
                    $detailsToInsert = []; 
                    $this->info("🔄 {$count} baris ditampung ke memori sementara...");
                }
            }

            // FIX #1390: INSERT SISA DATA DI LUAR LOOP - Chunk untuk menghindari error "too many placeholders"
            if (count($headersToInsert) > 0) {
                foreach (array_chunk($headersToInsert, 500) as $chunk) {
                    DB::table('journal_headers')->insert($chunk);
                }
            }
            if (count($detailsToInsert) > 0) {
                DB::table('journal_details')->insert($detailsToInsert);
            }

            // OPTIMASI 3: Tumpahkan semua data dari RAM ke Harddisk di detik terakhir!
            $this->info("🚀 Menyimpan permanen ke Database... (Harap tunggu beberapa detik)");
            DB::commit();

            fclose($handle);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->info("✅ BERHASIL SEMPURNA! Total {$count} baris transaksi selesai di-import.");

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika ada 1 saja yang error
            fclose($handle);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("❌ GAGAL! Terjadi kesalahan sistem: " . $e->getMessage());
        }
    }
}

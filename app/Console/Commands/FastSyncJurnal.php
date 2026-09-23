<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\JournalHeader;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\NumberParser;

class FastSyncJurnal extends Command
{
    protected $signature = 'jurnal:sync {file}';
    protected $description = 'Smart Sync Jurnal dari file CSV (Skala Big Data / Enterprise)';

    public function handle()
    {
        // 1. BEBASKAN LIMIT RAM UNTUK BIG DATA
        ini_set('memory_limit', '1024M'); 
        
        // 2. MATIKAN PENCATATAN LOG LARAVEL AGAR RAM TIDAK BOCOR
        DB::disableQueryLog(); 

        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("❌ File tidak ditemukan di jalur: {$filePath}");
            return;
        }

        $handle = fopen($filePath, "r");
        if (!$handle) return $this->error("❌ Gagal membuka file CSV.");

        fgets($handle);

        $this->info("🔍 FASE 1: Memindai rentang tanggal pada CSV (500k+ Baris)...");
        
        $minDate = null;
        $maxDate = null;
        $prefixes = [];
        $rawNoBuktiList = [];

        while (($rawLine = fgets($handle)) !== false) {
            if (trim($rawLine) === '') continue;

            $delimiter = str_contains($rawLine, ';') ? ';' : ',';
            $r = str_getcsv($rawLine, $delimiter, '"', '\\');
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

            if ($minDate === null || $tanggal < $minDate) $minDate = $tanggal;
            if ($maxDate === null || $tanggal > $maxDate) $maxDate = $tanggal;

            $evidenceNumber = empty($noJurnal) ? $noBukti : $noJurnal; 
            if (!empty($evidenceNumber) && str_contains($evidenceNumber, '-')) {
                $prefix = explode('-', $evidenceNumber)[0];
                $prefixes[$prefix] = true;
            }

            if (!empty($noBukti)) {
                $rawNoBuktiList[] = $noBukti;
            }
        }

        if (!$minDate || !$maxDate) return $this->error("❌ Tidak ada data valid di dalam CSV.");

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

        $this->info("📅 Ditemukan data dari tanggal {$minDate} s/d {$maxDate}");
        $this->info("🧹 FASE 2: Membersihkan data lama secara bertahap (Chunk Deleting)...");
        
        DB::beginTransaction();
        try {
            $prefixList = array_keys($prefixes);
            $query = JournalHeader::whereBetween('transaction_date', [$minDate, $maxDate])
                                  ->where('evidence_number', '!=', 'SA-00000');

            if (!empty($prefixList)) {
                $query->where(function($q) use ($prefixList) {
                    foreach($prefixList as $pf) {
                        $q->orWhere('evidence_number', 'LIKE', $pf . '-%');
                    }
                });
            }

            $headersToDelete = $query->pluck('journal_id');
            $deletedCount = $headersToDelete->count();

            if ($deletedCount > 0) {
                $headersToDelete->chunk(5000)->each(function ($chunk) {
                    DB::table('journal_details')->whereIn('journal_id', $chunk)->delete();
                    DB::table('journal_headers')->whereIn('journal_id', $chunk)->delete();
                });
                $this->info("🗑️ Berhasil menghapus {$deletedCount} Nomor Bukti lama.");
            } else {
                $this->info("✨ Tidak ada data lama yang perlu ditimpa. Lanjut Mode Insert.");
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("❌ Gagal membersihkan data lama: " . explode(' (Connection: mysql', $e->getMessage())[0]);
        }

        $this->info("🚀 FASE 3: Menyuntikkan 500.000+ data ke Database (Harap bersabar)...");
        
        rewind($handle);
        fgets($handle); 

        $detailsToInsert = [];
        $headersToInsert = []; // Tambahan array penampung header
        $count = 0;
        $now = now();
        $headerCache = [];
        $accountMapping = ['1133' => '11307'];

        // Pre-calculate ID Jurnal untuk mode Batch Insert
        $datePrefix = 'JRN-' . date('Ymd') . '-';
        $lastIdStr = DB::table('journal_headers')
                        ->where('journal_id', 'like', $datePrefix . '%')
                        ->orderByDesc('journal_id')
                        ->value('journal_id');
        $seq = $lastIdStr ? (int) substr($lastIdStr, -6) : 0;

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
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

                if (is_numeric($tanggal) && strlen($tanggal) <= 5) {
                    $tanggal = date('Y-m-d', Date::excelToTimestamp($tanggal));
                } else {
                    $tanggal = date('Y-m-d', strtotime(str_replace('/', '-', $tanggal)));
                }

                $explodeAkun = explode(' - ', $rawAkun);
                $kodeAkun = str_replace('-', '', trim($explodeAkun[0]));
                if (array_key_exists($kodeAkun, $accountMapping)) {
                    $kodeAkun = $accountMapping[$kodeAkun];
                }

                // FIX: DRY — gunakan NumberParser helper
                $debet  = NumberParser::parseDecimal($rawDebet);
                $kredit = NumberParser::parseDecimal($rawKredit);
                if ($debet == 0 && $kredit == 0) continue; 

                $evidenceNumber = empty($noJurnal) ? $noBukti : $noJurnal; 
                if (empty($evidenceNumber)) continue;

                if (!isset($headerCache[$evidenceNumber])) {
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

                // FIX 13: Ubah > 0 menjadi != 0
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

                // FIX 14: Ubah > 0 menjadi != 0
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

                // FIX #1390: Chunk Insert per 1000 baris
                // Chunk Insert per 1000 baris (Detail)
                if (count($detailsToInsert) >= 1000) {
                    // Wajib insert Header dulu agar Foreign Key tidak error
                    // Chunk headers to avoid "too many placeholders" error (MySQL limit: 65,535)
                    if (!empty($headersToInsert)) {
                        foreach (array_chunk($headersToInsert, 500) as $chunk) {
                            DB::table('journal_headers')->insert($chunk);
                        }
                        $headersToInsert = [];
                    }

                    DB::table('journal_details')->insert($detailsToInsert);
                    $detailsToInsert = []; 
                    
                    // Notifikasi hanya muncul per kelipatan 10.000 agar terminal tidak macet (lag)
                    if ($count % 10000 === 0) {
                        $this->info("🔄 {$count} baris transaksi berhasil diproses...");
                    }
                }
            }

            // FIX #1390: Bersihkan sisa data yang belum mencapai 1000 baris - Chunk untuk menghindari error "too many placeholders"
            if (!empty($headersToInsert)) {
                foreach (array_chunk($headersToInsert, 500) as $chunk) {
                    DB::table('journal_headers')->insert($chunk);
                }
            }
            if (!empty($detailsToInsert)) {
                DB::table('journal_details')->insert($detailsToInsert);
            }

            // FIX: Hapus DB::commit() ganda yang bisa menyebabkan error pada PDO
            DB::commit();

            fclose($handle);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->info("✅ SYNC BIG DATA BERHASIL! Total {$count} baris transaksi selesai di-import.");

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $errorMsg = $e->getMessage();
            $cleanError = explode(' (Connection: mysql', $errorMsg)[0];
            
            $this->error("❌ SYNC GAGAL: " . $cleanError);
        }
    }
}

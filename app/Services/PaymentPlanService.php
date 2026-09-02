<?php

namespace App\Services;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * DEPRECATED — DO NOT USE.
 * 
 * This service is ORPHAN code. All Payment Plan functionality is implemented
 * directly in PaymentPlanController. This file contains a fatal bug in
 * postToJournal() which credits Akun Piutang Usaha (asset) instead of Kas/Bank
 * for cash disbursements, and writes to non-existent DB columns in purchase_orders.
 * 
 * Kept in repo for reference only. If reactivation is needed, rewrite from scratch
 * following PaymentPlanController patterns.
 */
class PaymentPlanService
{
    /**
     * Generate nomor transaksi dengan retry untuk menghindari race condition.
     */
    public function generateNoTransaksi(string $idDivisi, string $tglPengajuan, string $jenisTransaksi, int $maxRetries = 5): string
    {
        $divisi = DB::table('master_divisi')->where('id_divisi', $idDivisi)->first();
        if (!$divisi) {
            throw new \Exception("Divisi dengan ID {$idDivisi} tidak ditemukan.");
        }

        $bulanTahun = date('my', strtotime($tglPengajuan));
        $tgl = date('d', strtotime($tglPengajuan));

        $jenisBersih = str_replace(' ', '', strtoupper($jenisTransaksi));
        $huruf1 = substr($jenisBersih, 0, 1) ?: 'X';
        $huruf2 = substr($jenisBersih, 1, 1) ?: 'X';
        $huruf4 = strlen($jenisBersih) >= 4 ? substr($jenisBersih, 3, 1) : 'X';
        $kodeJenis = $huruf1 . $huruf2 . $huruf4;

        $year = date('Y', strtotime($tglPengajuan));
        $month = date('m', strtotime($tglPengajuan));

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $existingNumbers = DB::table('transaksi_payment_plan')
                ->whereMonth('tgl_pengajuan', $month)
                ->whereYear('tgl_pengajuan', $year)
                ->pluck('no_transaksi')
                ->toArray();

            $existingSet = array_flip(array_map('strtoupper', $existingNumbers));
            $count = count($existingNumbers) + 1 + $attempt;

            $no_transaksi = "{$bulanTahun}.{$divisi->kode_divisi}.{$kodeJenis}.{$tgl}.{$count}";

            if (!isset($existingSet[strtoupper($no_transaksi)])) {
                return strtoupper($no_transaksi);
            }
        }

        return strtoupper("{$bulanTahun}.{$divisi->kode_divisi}.{$kodeJenis}.{$tgl}." . time());
    }


    /**
     * Store Payment Plan and optionally create Purchase Order
     */
    public function storePaymentPlan(array $data, $file = null, array $poDetails = null): string
    {
        DB::beginTransaction();
        try {
            $tgl_pengajuan = $data['tgl_pengajuan'] ?? now()->format('Y-m-d');
            $no_transaksi = $this->generateNoTransaksi($data['id_divisi'], $tgl_pengajuan, $data['jenis_transaksi']);

            $bukti_payment = null;
            if ($file) {
                $bukti_payment = $file->store('payment_plans/bukti', 'public');
            }

            DB::table('transaksi_payment_plan')->insert([
                'no_transaksi'    => $no_transaksi,
                'id_divisi'       => $data['id_divisi'],
                'id_akun'         => $data['id_akun'] ?? null,
                'tgl_pengajuan'   => $tgl_pengajuan,
                'jenis_transaksi' => $data['jenis_transaksi'],
                'kategori_payment' => $data['kategori_payment'],
                'vendor_toko'     => strtoupper($data['vendor_toko']),
                'penerima_pj'     => strtoupper($data['penerima_pj']),
                'rekening_va'     => strtoupper($data['rekening_va'] ?? '-'),
                'keterangan'      => $data['keterangan'],
                'nominal'         => $data['nominal'],
                'status_payment'  => $data['status_payment'] ?? 'PENGAJUAN',
                'bukti_payment'   => $bukti_payment,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if (!empty($poDetails)) {
                $po_number = $this->generateNoTransaksi($data['id_divisi'], $tgl_pengajuan, 'PO');
                
                DB::table('purchase_orders')->insert([
                    'po_number'     => $po_number,
                    'tgl_po'        => $tgl_pengajuan,
                    'id_divisi'     => $data['id_divisi'],
                    'vendor_toko'   => strtoupper($data['vendor_toko']),
                    'total_nominal' => $data['nominal'],
                    'status_po'     => 'DRAFT',
                    'no_transaksi_pp' => $no_transaksi,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $poId = DB::getPdo()->lastInsertId();
                $details = [];
                foreach ($poDetails as $item) {
                    $details[] = [
                        'po_id'       => $poId,
                        'item_name'   => $item['item_name'],
                        'qty'         => $item['qty'],
                        'price'       => $item['price'],
                        'total_price' => $item['qty'] * $item['price'],
                    ];
                }
                DB::table('purchase_order_details')->insert($details);
            }

            DB::commit();
            SystemLog::record('CREATE', 'Payment Plan', "Payment Plan berhasil dibuat: {$no_transaksi}");
            return $no_transaksi;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update Payment Plan and sync with PO
     */
    public function updatePaymentPlan(string $no_transaksi, array $data): bool
    {
        DB::beginTransaction();
        try {
            DB::table('transaksi_payment_plan')
                ->where('no_transaksi', $no_transaksi)
                ->update([
                    'id_akun'         => $data['id_akun'] ?? null,
                    'jenis_transaksi' => $data['jenis_transaksi'],
                    'kategori_payment' => $data['kategori_payment'],
                    'vendor_toko'     => strtoupper($data['vendor_toko']),
                    'penerima_pj'     => strtoupper($data['penerima_pj']),
                    'rekening_va'     => strtoupper($data['rekening_va'] ?? '-'),
                    'keterangan'      => $data['keterangan'],
                    'nominal'         => $data['nominal'],
                    'status_payment'  => $data['status_payment'],
                    'updated_at'      => now(),
                ]);

            if (isset($data['file'])) {
                $file = $data['file'];
                $path = $file->store('payment_plans/bukti', 'public');
                DB::table('transaksi_payment_plan')->where('no_transaksi', $no_transaksi)->update(['bukti_payment' => $path]);
            }

            $po = DB::table('purchase_orders')->where('no_transaksi_pp', $no_transaksi)->first();
            if ($po) {
                DB::table('purchase_orders')
                    ->where('id', $po->id)
                    ->update([
                        'vendor_toko'   => strtoupper($data['vendor_toko']),
                        'total_nominal' => $data['nominal'],
                        'updated_at'    => now(),
                    ]);

                if (!empty($data['po_details'])) {
                    DB::table('purchase_order_details')->where('po_id', $po->id)->delete();
                    $details = [];
                    foreach ($data['po_details'] as $item) {
                        $details[] = [
                            'po_id'       => $po->id,
                            'item_name'   => $item['item_name'],
                            'qty'         => $item['qty'],
                            'price'       => $item['price'],
                            'total_price' => $item['qty'] * $item['price'],
                        ];
                    }
                    DB::table('purchase_order_details')->insert($details);
                }
            }

            DB::commit();
            SystemLog::record('UPDATE', 'Payment Plan', "Payment Plan berhasil diupdate: {$no_transaksi}");
            return true;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete Payment Plan and related journals/POs
     */
    public function deletePaymentPlan(string $no_transaksi): bool
    {
        DB::beginTransaction();
        try {
            // FIX C4: Gunakan kolom description bukan keterangan
            $journals = DB::table('journal_headers')
                ->whereExists(function ($query) use ($no_transaksi) {
                    $query->select(DB::raw(1))
                        ->from('journal_details')
                        ->whereColumn('journal_details.journal_id', 'journal_headers.journal_id')
                        ->where('journal_details.description', 'like', "%{$no_transaksi}%");
                })->pluck('journal_id');

            if ($journals->isNotEmpty()) {
                DB::table('journal_details')->whereIn('journal_id', $journals)->delete();
                DB::table('journal_headers')->whereIn('journal_id', $journals)->delete();
            }

            $pos = DB::table('purchase_orders')->where('no_transaksi_pp', $no_transaksi)->pluck('id');
            if ($pos->isNotEmpty()) {
                DB::table('purchase_order_details')->whereIn('po_id', $pos)->delete();
                DB::table('purchase_orders')->whereIn('id', $pos)->delete();
            }

            DB::table('transaksi_payment_plan')->where('no_transaksi', $no_transaksi)->delete();

            DB::commit();
            SystemLog::record('DELETE', 'Payment Plan', "Payment Plan dihapus: {$no_transaksi}");
            return true;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Post multiple Payment Plans to Journal
     */
    public function postToJournal(array $no_transaksis): array
    {
        $results = ['success' => [], 'failed' => []];

        $accounts = DB::table('accounts')->pluck('id', 'account_code')->toArray();

        // N4 FIX: Pre-load semua transaksi payment plan dalam SATU query (whereIn + keyBy)
        // agar tidak terjadi N+1 (1 SELECT per no_transaksi di dalam loop).
        $ppMap = DB::table('transaksi_payment_plan')
            ->whereIn('no_transaksi', $no_transaksis)
            ->get()
            ->keyBy('no_transaksi');

        foreach ($no_transaksis as $no_transaksi) {
            DB::beginTransaction();
            try {
                // N4 FIX: Ambil dari map in-memory, bukan query baru per iterasi
                $pp = $ppMap->get($no_transaksi);
                if (!$pp) throw new \Exception("Transaksi {$no_transaksi} tidak ditemukan.");
                if ($pp->status_payment === 'POSTED') {
                    throw new \Exception("Transaksi {$no_transaksi} sudah di-post ke jurnal.");
                }

                // FIX: ID deterministik berbasis no_transaksi (bukan rand()/time())
                $journalId = JournalHeader::idForPaymentPlan($no_transaksi);
                $journalNo = "JRN-PP-" . date('Ymd') . "-" . substr(preg_replace('/[^0-9]/', '', $no_transaksi), -4);

                // FIX C4: Gunakan kolom yang benar sesuai skema database
                DB::table('journal_headers')->insert([
                    'journal_id'       => $journalId,
                    'journal_no'     => $journalNo,
                    'transaction_date' => now()->toDateString(),
                    'description'      => "Posting Payment Plan: {$no_transaksi} - {$pp->vendor_toko}",
                    'source_doc_no'    => $no_transaksi,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                // FIX C4: validasi akun harus ada di master COA
                $accountCode = $pp->id_akun;
                $accountExists = DB::table('accounts')->where('account_code', $accountCode)->exists();
                if (!$accountExists) {
                    throw new \Exception("Akun {$accountCode} tidak ditemukan di master accounts.");
                }

                DB::table('journal_details')->insert([
                    'journal_id'   => $journalId,
                    'journal_no'   => $journalNo,
                    'account_code' => $accountCode,
                    'helper_code'  => null,
                    'position'     => 'DEBET',
                    'amount'       => $pp->nominal,
                    'description'  => "Payment Plan: {$no_transaksi}",
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // FIX C4: Gunakan kode akun kas yang valid dari konfigurasi COA
                $cashAccount = config('coa.piutang_usaha');
                $cashAccountExists = DB::table('accounts')->where('account_code', $cashAccount)->exists();
                if (!$cashAccountExists) {
                    throw new \Exception("Kode akun kas {$cashAccount} tidak ditemukan di master accounts.");
                }

                DB::table('journal_details')->insert([
                    'journal_id'   => $journalId,
                    'journal_no'   => $journalNo,
                    'account_code' => $cashAccount,
                    'helper_code'  => null,
                    'position'     => 'KREDIT',
                    'amount'       => $pp->nominal,
                    'description'  => "Payment Plan: {$no_transaksi}",
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                DB::table('transaksi_payment_plan')
                    ->where('no_transaksi', $no_transaksi)
                    ->update([
                        'status_payment' => 'POSTED',
                        'journal_id'     => $journalId,
                    ]);

                DB::commit();
                $results['success'][] = $no_transaksi;
            } catch (Throwable $e) {
                DB::rollBack();
                $results['failed'][] = ['no' => $no_transaksi, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Import Payment Plans from CSV
     */
    public function importFromCsv($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ',');

        $inserted = 0;
        $failed = 0;
        $errorMessages = [];
        $batchData = [];

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            try {
                $row = array_combine($header, $data);
                
                if (empty($row['id_divisi']) || empty($row['nominal'])) {
                    throw new \Exception("Kolom wajib (id_divisi, nominal) kosong.");
                }

                $tgl_pengajuan = $row['tgl_pengajuan'] ?? now()->format('Y-m-d');
                $no_transaksi = $this->generateNoTransaksi($row['id_divisi'], $tgl_pengajuan, $row['jenis_transaksi'] ?? 'KAS');

                $batchData[] = [
                    'no_transaksi'    => $no_transaksi,
                    'id_divisi'       => $row['id_divisi'],
                    'id_akun'         => $row['id_akun'] ?? null,
                    'tgl_pengajuan'   => $tgl_pengajuan,
                    'jenis_transaksi' => $row['jenis_transaksi'] ?? 'KAS',
                    'kategori_payment' => $row['kategori_payment'] ?? 'OPERASIONAL',
                    'vendor_toko'     => strtoupper($row['vendor_toko'] ?? '-'),
                    'penerima_pj'     => strtoupper($row['penerima_pj'] ?? '-'),
                    'rekening_va'     => strtoupper($row['rekening_va'] ?? '-'),
                    'keterangan'      => $row['keterangan'] ?? '-',
                    'nominal'         => (float)$row['nominal'],
                    'status_payment'  => 'PENGAJUAN',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                $inserted++;
            } catch (Throwable $e) {
                $failed++;
                $errorMessages[] = $e->getMessage();
            }

            if (count($batchData) >= 100) {
                DB::table('transaksi_payment_plan')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('transaksi_payment_plan')->insert($batchData);
        }

        fclose($handle);

        return [
            'inserted' => $inserted,
            'failed' => $failed,
            'errors' => $errorMessages
        ];
    }
}

 

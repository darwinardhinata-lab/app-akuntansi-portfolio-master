<?php

namespace App\Http\Controllers;

use App\Models\PaymentPlan;
use App\Models\MasterDivisi;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\PaymentCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PaymentPlanController extends Controller
{
    private function generateNoTransaksi(string $idDivisi, string $tglPengajuan, string $jenisTransaksi, int $maxRetries = 5): string
    {
        $divisi = MasterDivisi::findOrFail($idDivisi);
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
            $existingNumbers = PaymentPlan::whereMonth('tgl_pengajuan', $month)
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

    public function index(Request $request)
    {
        $search = $request->input('search');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $filter_divisi = $request->input('id_divisi');
        $filter_status = $request->input('status_payment');
        $filter_kategori = $request->input('kategori_payment');

        $paymentPlansQuery = PaymentPlan::with(['divisi', 'account', 'details'])
            ->selectRaw('transaksi_payment_plan.*, SUM(COALESCE(nominal_aktual, nominal)) OVER (PARTITION BY DATE_FORMAT(tgl_pengajuan, "%Y-%m") ORDER BY tgl_pengajuan ASC, id_payment ASC) as total_kumulatif_bulan')
            ->when($search, function ($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('no_transaksi', 'like', "%{$search}%")
                      ->orWhere('vendor_toko', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('tgl_pengajuan', [$start_date, $end_date]);
            })
            ->when($filter_divisi, function ($query) use ($filter_divisi) {
                $query->where('id_divisi', $filter_divisi);
            })
            ->when($filter_status, function ($query) use ($filter_status) {
                $query->where('status_payment', $filter_status);
            })
            ->when($filter_kategori, function ($query) use ($filter_kategori) {
                $query->where('kategori_payment', $filter_kategori);
            })
            ->orderBy('tgl_pengajuan', 'desc')
            ->orderBy('id_payment', 'desc');

        $payment_plans = $paymentPlansQuery->paginate(50)->withQueryString();

        $currentMonth = date('m');
        $currentYear = date('Y');

        $statsQuery = PaymentPlan::query()
            ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                $q->whereBetween('tgl_pengajuan', [$start_date, $end_date]);
            }, function ($q) use ($currentMonth, $currentYear) {
                $q->whereMonth('tgl_pengajuan', $currentMonth)->whereYear('tgl_pengajuan', $currentYear);
            })
            ->when($filter_divisi, fn($q) => $q->where('id_divisi', $filter_divisi))
            ->when($filter_kategori, fn($q) => $q->where('kategori_payment', $filter_kategori));

        $stats = [
            'total_nominal_bulan' => (float) (clone $statsQuery)->sum(DB::raw('COALESCE(nominal_aktual, nominal)')),
            'total_approved'      => (float) (clone $statsQuery)->whereIn('status_payment', ['APPROVED', 'PAID', 'POSTED'])->sum(DB::raw('COALESCE(nominal_aktual, nominal)')),
            'total_pending'       => (float) (clone $statsQuery)->where('status_payment', 'PENGAJUAN')->sum(DB::raw('COALESCE(nominal_aktual, nominal)')),
            'total_count'         => (int) (clone $statsQuery)->count(),
        ];

        $master_divisi = MasterDivisi::where('status_aktif', 1)->orderBy('nama_divisi', 'asc')->get();
        $payment_categories = PaymentCategory::active()->orderBy('name', 'asc')->get();

        return view('payment_plan.index', compact(
            'payment_plans', 'search', 'start_date', 'end_date',
            'filter_divisi', 'filter_status', 'filter_kategori',
            'master_divisi', 'payment_categories', 'stats'
        ));
    }

    public function create()
    {
        $divisi = MasterDivisi::where('status_aktif', 1)->orderBy('nama_divisi', 'asc')->get();
        $purchaseOrders = PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIAL', 'RECEIVED'])
            ->orderBy('transaction_date', 'desc')->get();
        $payment_categories = PaymentCategory::active()->orderBy('name', 'asc')->get();

        return view('payment_plan.create', compact('divisi', 'purchaseOrders', 'payment_categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_divisi' => 'required|exists:master_divisi,id_divisi',
            'tgl_pengajuan' => 'required|date',
            'tgl_transaksi' => 'nullable|date',
            'jenis_transaksi' => 'required',
            'kategori_payment' => 'required',
            'vendor_toko' => 'required',
            'penerima_pj' => 'required',
            'nama_toko_link' => 'nullable|string|max:150',

            // item-item
            'items' => 'required|array|min:1',
            'items.*.nama_item' => 'nullable|string|max:255',
            'items.*.keterangan' => 'required|string',
            'items.*.qty' => 'nullable|numeric|min:0.01',
            'items.*.harga_satuan' => 'nullable|numeric|min:0',
            'items.*.satuan' => 'nullable|string|max:20',
            'items.*.nominal' => 'required_without:items.*.harga_satuan|nullable|numeric|min:0',
            'items.*.bukti_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        DB::beginTransaction();

        try {
            $no_transaksi = $this->generateNoTransaksi(
                $request->id_divisi,
                $request->tgl_pengajuan,
                $request->jenis_transaksi
            );

            $paymentPlan = PaymentPlan::create([
                'no_transaksi' => $no_transaksi,
                'id_divisi' => $request->id_divisi,
                'id_akun' => null,
                'tgl_pengajuan' => $request->tgl_pengajuan,
                'tgl_transaksi' => $request->tgl_transaksi ?? $request->tgl_pengajuan,
                'jatuh_tempo' => $request->jatuh_tempo,
                'jenis_transaksi' => strtoupper($request->jenis_transaksi),
                'kategori_payment' => strtoupper($request->kategori_payment),
                'vendor_toko' => strtoupper($request->vendor_toko),
                'penerima_pj' => strtoupper($request->penerima_pj),
                'rekening_va' => strtoupper($request->rekening_va),
                'keterangan' => $request->keterangan ?? ('Pengajuan ' . count($request->items) . ' item'),
                'nominal' => 0, // akan di-recalc di bawah
                'status_payment' => 'PENGAJUAN',
                'nama_toko_link' => $request->nama_toko_link,
            ]);

            foreach ($request->items as $idx => $item) {
                $filePath = null;
                if ($request->hasFile("items.$idx.bukti_file")) {
                    $file = $request->file("items.$idx.bukti_file");
                    $filename = time() . '_' . $idx . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '', $file->getClientOriginalName());
                    $filePath = $file->storeAs('bukti_payment', $filename, 'public');
                }

                $qty = $item['qty'] ?? 1;
                $hargaSatuan = $item['harga_satuan'] ?? null;
                $nominal = ($hargaSatuan !== null && $hargaSatuan !== '')
                    ? (float) $hargaSatuan * (float) $qty
                    : (float) ($item['nominal'] ?? 0);

                $paymentPlan->details()->create([
                    'nama_item' => $item['nama_item'] ?? null,
                    'qty' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'satuan' => $item['satuan'] ?? 'Pcs',
                    'keterangan' => $item['keterangan'],
                    'bukti_file' => $filePath,
                    'nominal' => $nominal,
                ]);
            }

            $paymentPlan->recalcFromDetails();
            $paymentPlan->refresh();

            // FIX #4: Gunakan PO asli jika disediakan, fallback ke sintetis untuk backward compatibility
            $realPoNumber = $request->input('ref_po_number');
            if ($request->kategori_payment === 'PEMBELIAN PERSEDIAAN (UANG MUKA)' && $request->has('po_details')) {
                $subTotalPO = 0;
                foreach ($request->po_details as $det) {
                    if (isset($det['qty']) && $det['qty'] > 0) {
                        $subTotalPO += ($det['price'] * $det['qty']);
                    }
                }

                $existingRealPo = null;
                if ($realPoNumber) {
                    $existingRealPo = PurchaseOrder::where('po_number', $realPoNumber)->first();
                }

                $poNumberToUse = $existingRealPo ? $existingRealPo->po_number : ('PO-' . $no_transaksi);

                $po = PurchaseOrder::firstOrCreate(
                    ['po_number' => $poNumberToUse],
                    [
                        'transaction_date' => $request->tgl_pengajuan,
                        'contact_name'     => strtoupper($request->vendor_toko),
                        'location_name'    => 'Pusat',
                        'status'           => 'APPROVED',
                        'sub_total'        => $subTotalPO,
                        'grand_total'      => $paymentPlan->nominal,
                    ]
                );

                if ($existingRealPo && $po->wasRecentlyCreated) {
                    $paymentPlan->update(['ref_po_number' => $poNumberToUse]);
                }

                $skus = collect($request->po_details)
                    ->pluck('item_code')->filter()->unique()->values()->toArray();
                $productsMap = \App\Models\Product::whereIn('sku', $skus)
                    ->get(['id', 'sku', 'name'])->keyBy('sku');

                $detailsToInsert = [];
                $now = now();
                foreach ($request->po_details as $det) {
                    if (!empty($det['item_code']) && $det['qty'] > 0) {
                        $product = $productsMap->get($det['item_code']);
                        $detailsToInsert[] = [
                            'purchase_order_id' => $po->id,
                            'product_id'        => $product?->id,
                            'item_code'         => $det['item_code'],
                            'description'       => $det['description'] ?? ($product?->name ?? '-'),
                            'price'             => $det['price'],
                            'qty'               => $det['qty'],
                            'qty_received'      => 0,
                            'amount'            => $det['price'] * $det['qty'],
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ];
                    }
                }

                if (count($detailsToInsert) > 0) {
                    PurchaseOrderDetail::insert($detailsToInsert);
                }
            }

            DB::commit();
            SystemLog::record('CREATE', 'Payment Plan', 'Menambahkan pengajuan: ' . $no_transaksi . ' - ' . $request->vendor_toko . ' (' . count($request->items) . ' item)');

            return redirect()->route('payment.index')->with('success', "Pengajuan Berhasil Disimpan dengan No: {$no_transaksi}");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pengajuan: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $data = PaymentPlan::where('id_payment', $id)->first();
        if (!$data) {
            return redirect()->route('payment.index')->with('error', 'Data tidak ditemukan!');
        }
        $divisi = MasterDivisi::where('status_aktif', 1)->orderBy('nama_divisi', 'asc')->get();
        return view('payment_plan.edit', compact('data', 'divisi'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_divisi' => 'required',
            'tgl_pengajuan' => 'required|date',
            'tgl_transaksi' => 'nullable|date',
            'jenis_transaksi' => 'required',
            'kategori_payment' => 'required',
            'vendor_toko' => 'required',
            'penerima_pj' => 'required',
            'nama_toko_link' => 'nullable|string|max:150',

            'items' => 'required|array|min:1',
            'items.*.id_detail' => 'nullable|integer|exists:transaksi_payment_plan_detail,id_detail',
            'items.*.nama_item' => 'nullable|string|max:255',
            'items.*.keterangan' => 'required|string',
            'items.*.qty' => 'nullable|numeric|min:0.01',
            'items.*.harga_satuan' => 'nullable|numeric|min:0',
            'items.*.satuan' => 'nullable|string|max:20',
            'items.*.nominal' => 'required_without:items.*.harga_satuan|nullable|numeric|min:0',
            'items.*.nominal_aktual' => 'nullable|numeric|min:0',
            'items.*.bukti_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'deleted_detail_ids' => 'nullable|array',
            'deleted_detail_ids.*' => 'integer',
        ]);

        $current = PaymentPlan::where('id_payment', $id)->first();
        if (!$current) {
            return redirect()->route('payment.index')->with('error', 'Data tidak ditemukan!');
        }

        // Hitung dulu total nominal & nominal_aktual BARU dari payload items,
        // supaya bisa dibandingkan dengan yang lama sebelum benar-benar menyimpan
        // (untuk aturan "terkunci setelah PAID/POSTED").
        $totalNominalBaru = 0;
        $adaAktualDiisiBaru = false;
        foreach ($request->items as $item) {
            $qty = $item['qty'] ?? 1;
            $hargaSatuan = $item['harga_satuan'] ?? null;
            $nominalItem = ($hargaSatuan !== null && $hargaSatuan !== '')
                ? (float) $hargaSatuan * (float) $qty
                : (float) ($item['nominal'] ?? 0);
            $totalNominalBaru += $nominalItem;

            if (!empty($item['nominal_aktual']) || $item['nominal_aktual'] === '0') {
                $adaAktualDiisiBaru = true;
            }
        }

        $statusTerkunci = ($current->status_payment === 'PAID' || $current->status_payment === 'POSTED');
        $nominalBerubah = round($totalNominalBaru, 2) != round((float) $current->nominal, 2);

        if ($statusTerkunci && $nominalBerubah) {
            return redirect()->back()->with('error', 'Tidak dapat mengubah Nominal Pengajuan/Aktual untuk payment plan yang sudah diposting ke Jurnal. Gunakan Jurnal Penyesuaian manual jika ada selisih yang perlu dikoreksi setelah posting.');
        }

        DB::beginTransaction();
        try {
            $oldKategori = $current->kategori_payment;

            $current->update([
                'id_divisi' => $request->id_divisi,
                'tgl_pengajuan' => $request->tgl_pengajuan,
                'tgl_transaksi' => $request->tgl_transaksi ?? $request->tgl_pengajuan,
                'jatuh_tempo' => $request->jatuh_tempo,
                'jenis_transaksi' => strtoupper($request->jenis_transaksi),
                'kategori_payment' => strtoupper($request->kategori_payment),
                'vendor_toko' => strtoupper($request->vendor_toko),
                'penerima_pj' => strtoupper($request->penerima_pj),
                'rekening_va' => strtoupper($request->rekening_va),
                'keterangan' => $request->keterangan ?? $current->keterangan,
                'nama_toko_link' => $request->nama_toko_link,
            ]);

            // Hapus detail yang dibuang user di UI
            if ($request->filled('deleted_detail_ids')) {
                $current->details()
                    ->whereIn('id_detail', $request->deleted_detail_ids)
                    ->delete();
            }

            // Update existing / insert baru
            foreach ($request->items as $idx => $item) {
                $qty = $item['qty'] ?? 1;
                $hargaSatuan = $item['harga_satuan'] ?? null;
                $nominal = ($hargaSatuan !== null && $hargaSatuan !== '')
                    ? (float) $hargaSatuan * (float) $qty
                    : (float) ($item['nominal'] ?? 0);

                $payload = [
                    'nama_item' => $item['nama_item'] ?? null,
                    'qty' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'satuan' => $item['satuan'] ?? 'Pcs',
                    'keterangan' => $item['keterangan'],
                    'nominal' => $nominal,
                    'nominal_aktual' => ($item['nominal_aktual'] ?? null) !== null && $item['nominal_aktual'] !== ''
                        ? (float) $item['nominal_aktual']
                        : null,
                ];

                if ($request->hasFile("items.$idx.bukti_file")) {
                    $file = $request->file("items.$idx.bukti_file");
                    $filename = time() . '_' . $idx . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '', $file->getClientOriginalName());
                    $payload['bukti_file'] = $file->storeAs('bukti_payment', $filename, 'public');
                }

                if (!empty($item['id_detail'])) {
                    $current->details()->where('id_detail', $item['id_detail'])->update($payload);
                } else {
                    $current->details()->create($payload);
                }
            }

            $current->recalcFromDetails();
            $current->refresh();

            if ($oldKategori === 'PEMBELIAN PERSEDIAAN (UANG MUKA)' || $request->kategori_payment === 'PEMBELIAN PERSEDIAAN (UANG MUKA)') {
                $poNumber = 'PO-' . $current->no_transaksi;
                $po = PurchaseOrder::where('po_number', $poNumber)->first();
                if ($po) {
                    $po->update([
                        'transaction_date' => $request->tgl_pengajuan,
                        'contact_name'     => strtoupper($request->vendor_toko),
                        'grand_total'      => $current->nominal,
                    ]);
                }
            }

            DB::commit();
            SystemLog::record('UPDATE', 'Payment Plan', 'Mengubah data pengajuan: ' . $current->no_transaksi);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }

        return redirect()->route('payment.index')->with('success', 'Data Payment Plan berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $current = PaymentPlan::with('details')->where('id_payment', $id)->first();
        if (!$current) {
            return redirect()->back()->with('error', 'Data tidak ditemukan!');
        }

        DB::beginTransaction();
        try {
            if ($current->status_payment === 'PAID' || $current->status_payment === 'POSTED') {
                $journalIds = JournalHeader::where('evidence_number', 'JRN-' . $current->no_transaksi)->pluck('journal_id');
                if ($journalIds->isNotEmpty()) {
                    JournalDetail::whereIn('journal_id', $journalIds)->delete();
                    JournalHeader::whereIn('journal_id', $journalIds)->delete();
                }
            }

            $poNumber = 'PO-' . $current->no_transaksi;
            $poIds = PurchaseOrder::where('po_number', $poNumber)->pluck('id');
            if ($poIds->isNotEmpty()) {
                PurchaseOrderDetail::whereIn('purchase_order_id', $poIds)->delete();
                PurchaseOrder::whereIn('id', $poIds)->delete();
            }

            foreach ($current->details as $detail) {
                if ($detail->bukti_file) {
                    \Storage::disk('public')->delete($detail->bukti_file);
                }
            }

            $current->delete(); // detail ikut terhapus via FK cascade

            DB::commit();
            SystemLog::record('DELETE', 'Payment Plan', 'Menghapus pengajuan beserta turunannya: ' . $current->no_transaksi);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Data Payment Plan beserta seluruh turunannya (Jurnal & PO) berhasil dihapus bersih dari sistem!');
    }

    public function setCoa(Request $request, $id)
    {
        $request->validate(['id_akun' => 'required']);
        $current = PaymentPlan::where('id_payment', $id)->first();
        if (!$current) {
            return redirect()->back()->with('error', 'Data tidak ditemukan!');
        }
        $current->update([
            'id_akun' => $request->id_akun,
            'updated_at' => now()
        ]);
        SystemLog::record('UPDATE', 'Payment Plan', 'Menetapkan COA untuk pengajuan: ' . $current->no_transaksi);
        return redirect()->back()->with('success', 'COA (Akun Biaya) berhasil ditetapkan!');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status_payment' => 'required|in:PENGAJUAN,APPROVED,REJECTED,PAID,POSTED'
        ]);

        $current = PaymentPlan::where('id_payment', $id)->firstOrFail();

        $current->update([
            'status_payment' => $request->status_payment,
            'updated_at' => now()
        ]);

        SystemLog::record('UPDATE', 'Payment Plan', 'Mengubah status menjadi ' . $request->status_payment . ' untuk pengajuan: ' . $current->no_transaksi);
        return redirect()->back()->with('success', 'Status Payment Plan berhasil diubah menjadi ' . $request->status_payment . '!');
    }

    public function postJournal(Request $request)
    {
        $no_transaksis = explode(',', $request->selected_ids);

        if (empty($no_transaksis[0])) {
            return redirect()->back()->with('error', 'Pilih minimal satu data untuk diposting!');
        }

        $cashAccounts = Account::where(function($q) {
                $q->where('coa_type', 'like', '%Cash%')
                  ->orWhere('coa_type', 'like', '%Bank%')
                  ->orWhere('account_name', 'like', '%Kas%')
                  ->orWhere('account_name', 'like', '%Bank%');
            })
            ->where(DB::raw('LEFT(TRIM(account_code), 1)'), '1')
            ->orderBy('account_code', 'asc')
            ->get()
            ->keyBy('account_code');

        $cachedBankAccount = null;
        $cachedKasAccount = null;
        foreach ($cashAccounts as $acc) {
            if ($cachedBankAccount === null && (str_starts_with(trim($acc->account_code), '112') || str_contains(strtolower($acc->account_name), 'bank'))) {
                $cachedBankAccount = $acc->account_code;
            }
            if ($cachedKasAccount === null && (str_starts_with(trim($acc->account_code), '111') || str_contains(strtolower($acc->account_name), 'kas'))) {
                $cachedKasAccount = $acc->account_code;
            }
            if ($cachedBankAccount !== null && $cachedKasAccount !== null) break;
        }
        $defaultCashAccount = $cashAccounts->first()?->account_code ?? '11000';

        $payments = PaymentPlan::whereIn('no_transaksi', $no_transaksis)->get();
        $count    = 0;
        $skipped  = [];

        $candidateIds = $payments->pluck('no_transaksi')
            ->mapWithKeys(fn($no) => [$no => JournalHeader::idForPaymentPlan($no)]);
        $existingIds = JournalHeader::whereIn('journal_id', $candidateIds->values()->all())
            ->pluck('journal_id')
            ->flip()
            ->all();

        foreach ($payments as $item) {
            if (empty($item->id_akun)) {
                $skipped[] = $item->no_transaksi;
                continue;
            }

            $jenis = strtoupper($item->jenis_transaksi ?? '');
            $selectedKasAccount = str_contains($jenis, 'KAS') ? ($cachedKasAccount ?? $defaultCashAccount) : ($cachedBankAccount ?? $defaultCashAccount);

            // FIX PEMBAYARAN HUTANG:aksa debit ke akun Hutang Usaha, bukan ke akun yang dipilih staff
            $debitAccount = $item->id_akun;
            $kategori = strtoupper($item->kategori_payment ?? '');
            if (str_contains($kategori, 'PEMBAYARAN HUTANG')) {
                $debitAccount = config('coa.hutang_usaha');
                
                // Validasi: PEMBAYARAN HUTANG harus ada ref_bill_number
                if (empty($item->ref_bill_number)) {
                    $skipped[] = $item->no_transaksi . ' (PEMBAYARAN HUTANG butuh nomor Bill)';
                    continue;
                }
            }

            $journalId = $candidateIds[$item->no_transaksi];

            if (isset($existingIds[$journalId])) {
                $skipped[] = $item->no_transaksi . ' (sudah diposting)';
                continue;
            }

            if ($item->status_payment === 'POSTED') {
                $skipped[] = $item->no_transaksi . ' (sudah diposting)';
                continue;
            }

            if (empty($item->tgl_transaksi)) {
                $skipped[] = $item->no_transaksi . ' (tanggal transaksi belum diisi)';
                continue;
            }

            DB::beginTransaction();
            try {
                $transactionDate = $item->tgl_transaksi;

                JournalHeader::create([
                    'journal_id'       => $journalId,
                    'transaction_date' => $transactionDate,
                    'evidence_number'  => 'JRN-' . $item->no_transaksi,
                    'description'      => 'Payment Plan: ' . $item->keterangan,
                ]);

                // FIX: Jurnal WAJIB pakai nominal aktual efektif (COALESCE(nominal_aktual, nominal)),
                // bukan nominal pengajuan mentah. Jika Finance belum mengisi Nominal Aktual,
                // accessor ini otomatis fallback ke nominal pengajuan (tidak mengubah perilaku lama).
                $jumlahDijurnal = $item->nominal_aktual_efektif;

                JournalDetail::create([
                    'journal_id'   => $journalId,
                    'account_code' => $debitAccount,
                    'position'     => 'DEBET',
                    'amount'       => $jumlahDijurnal,
                ]);

                JournalDetail::create([
                    'journal_id'   => $journalId,
                    'account_code' => $selectedKasAccount,
                    'position'     => 'KREDIT',
                    'amount'       => $jumlahDijurnal,
                ]);

                $updateData = [
                    'status_payment' => 'POSTED',
                    'updated_at'     => now(),
                ];

                // FIX: Update purchase_bills.payment_status for PEMBAYARAN HUTANG
                if ($kategori === 'PEMBAYARAN HUTANG' && $item->ref_bill_number) {
                    $bill = \App\Models\PurchaseBill::where('bill_number', $item->ref_bill_number)->first();
                    if ($bill) {
                        $bill->update(['payment_status' => 'PAID']);
                    }
                }

                $item->update($updateData);

                DB::commit();
                $count++;

            } catch (\Exception $e) {
                DB::rollBack();
                $skipped[] = $item->no_transaksi . ' (error: ' . $e->getMessage() . ')';
            }
        }

        if ($count == 0) {
            return redirect()->back()->with('error', 'Gagal posting! Pastikan data yang dicentang sudah memiliki Alokasi Akun (COA).');
        }

        SystemLog::record('POST', 'Payment Plan', 'Posting ' . $count . ' data payment plan ke Jurnal Umum.');

        $msg = "$count Data Payment Plan berhasil diposting ke Jurnal Umum!";
        if (!empty($skipped)) {
            $msg .= ' ' . count($skipped) . ' data dilewati: ' . implode(', ', $skipped);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=TEMPLATE_IMPORT_PAYMENT_PLAN.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = [
            'NO PP', 'TANGGAL', 'KATEGORI PAYMENT', 'REKENING OPS', 'STATUS PAYMENT',
            'NAMA (PJ)', 'DIVISI', 'PEMASOK/TOKO', 'Nama Toko / Link', 'KETERANGAN',
            'NO VA / Rekening / Kode bayar', 'Detil Akun', 'QTY', 'Satuan', 'PENGAJUAN (Rp)',
            'AKTUAL (Rp)', 'SELISIH (Rp)', 'TOTAL (Rp)'
        ];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns, ';');
            $contoh = [
                '[auto]', '16/05/2026', 'PEMBELIAN & OPERASIONAL', 'BCA BBW OPS', 'APPROVED',
                'Nama Staff', 'FINANCE', 'Toko ABC', 'Shopee', 'Pembelian ATK',
                'BCA 123456', '51001', '1', 'Pcs', '150000', '150000', '0', '150000'
            ];
            fputcsv($file, $contoh, ';');
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        set_time_limit(300);
        ini_set('auto_detect_line_endings', true);
        $request->validate(['file_csv' => 'required|file']);
        $file = $request->file('file_csv');
        $handle = fopen($file->getRealPath(), 'r');

        $inserted = 0;
        $failed = 0;
        $skipped = [];
        $errorMessages = [];

        $firstLine = fgets($handle);
        if (!$firstLine) {
            return back()->with('success', 'GAGAL: File CSV kosong atau tidak terbaca formatnya.');
        }
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($handle);

        $monthlyCounts = [];
        $defaultDivisiId = MasterDivisi::value('id_divisi') ?? 1;

        $allDivisi = MasterDivisi::all();
        $divisiCache = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 4000, $delimiter)) !== false) {
                try {
                    $no_pp = trim($row[0] ?? '');
                    $tanggal = trim($row[1] ?? '');

                    if (empty($tanggal) || strtoupper($no_pp) == 'NO PP' || str_contains(strtoupper($tanggal), 'TANGGAL')) {
                        continue;
                    }

                    $kategori = trim($row[2] ?? '') ?: 'LAINNYA';
                    $rekening_ops = trim($row[3] ?? '') ?: 'PENDING';
                    $status = strtoupper(trim($row[4] ?? ''));
                    $nama_pj = trim($row[5] ?? '') ?: '-';
                    $divisi_str = trim($row[6] ?? '');
                    $vendor = trim($row[7] ?? '') ?: '-';
                    $nama_toko_link = trim($row[8] ?? '');
                    $keterangan = trim($row[9] ?? '') ?: '-';
                    $rekening_va = trim($row[10] ?? '') ?: '-';
                    $detil_akun = trim($row[11] ?? '');
                    $qty_raw = trim($row[12] ?? '1');
                    $satuan = trim($row[13] ?? '') ?: 'Pcs';
                    $nominal_raw = trim($row[14] ?? '0');
                    $nominal_aktual_raw = trim($row[15] ?? '');

                    $qty = (is_numeric($qty_raw) && (float)$qty_raw > 0) ? (float)$qty_raw : 1;

                    $nominal_clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $nominal_raw));
                    $nominal = (is_numeric($nominal_clean) && $nominal_clean !== '') ? (float) $nominal_clean : 0;

                    $nominal_aktual = null;
                    if ($nominal_aktual_raw !== '') {
                        $aktual_clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $nominal_aktual_raw));
                        if (is_numeric($aktual_clean) && $aktual_clean !== '') {
                            $nominal_aktual = (float) $aktual_clean;
                        }
                    }

                    if (is_numeric($tanggal) && strlen($tanggal) <= 5) {
                        $parsedDate = date('Y-m-d', Date::excelToTimestamp($tanggal));
                    } else {
                        $tanggal = str_replace('/', '-', $tanggal);
                        $parsedDate = date('Y-m-d', strtotime($tanggal));
                    }

                    if ($status == 'APPROVE') $status = 'APPROVED';
                    if ($status == 'REJECT') $status = 'REJECTED';
                    if (!in_array($status, ['PENGAJUAN', 'APPROVED', 'REJECTED', 'PAID', 'POSTED'])) {
                        $status = 'PENGAJUAN';
                    }

                    $divisi = null;
                    if (!empty($divisi_str)) {
                        if (isset($divisiCache[$divisi_str])) {
                            $divisi = $divisiCache[$divisi_str];
                        } else {
                            $divisi = $allDivisi->first(function($d) use ($divisi_str) {
                                return stripos($d->nama_divisi, $divisi_str) !== false
                                    || stripos($d->kode_divisi, $divisi_str) !== false;
                            });
                            $divisiCache[$divisi_str] = $divisi;
                        }
                    }

                    $id_divisi = $divisi ? $divisi->id_divisi : $defaultDivisiId;

                    if (!isset($existingNoSet)) {
                        $existingNoSet = [];
                        $existingNos = PaymentPlan::pluck('no_transaksi')->toArray();
                        foreach ($existingNos as $existingNo) {
                            $existingNoSet[strtoupper($existingNo)] = true;
                        }
                    }

                    if (empty($no_pp) || strtoupper($no_pp) === '[AUTO]') {
                        $div_kode = $divisi ? $divisi->kode_divisi : 'FIN';
                        $bulanTahun = date('my', strtotime($parsedDate));
                        $bulanKey = date('Y-m', strtotime($parsedDate));
                        $tgl = date('d', strtotime($parsedDate));

                        $jenisBersih = str_replace(' ', '', strtoupper($rekening_ops));
                        $huruf1 = substr($jenisBersih, 0, 1) ?: 'X';
                        $huruf2 = substr($jenisBersih, 1, 1) ?: 'X';
                        $huruf4 = strlen($jenisBersih) >= 4 ? substr($jenisBersih, 3, 1) : 'X';
                        $kodeJenis = $huruf1 . $huruf2 . $huruf4;

                        if (!isset($monthlyCounts[$bulanKey])) {
                            $monthlyCounts[$bulanKey] = PaymentPlan::whereMonth('tgl_pengajuan', date('m', strtotime($parsedDate)))
                                ->whereYear('tgl_pengajuan', date('Y', strtotime($parsedDate)))
                                ->count();
                        }

                        $generated = false;
                        for ($attempt = 0; $attempt < 5 && !$generated; $attempt++) {
                            $monthlyCounts[$bulanKey]++;
                            $count = $monthlyCounts[$bulanKey];
                            $candidate = "{$bulanTahun}.{$div_kode}.{$kodeJenis}.{$tgl}.{$count}";

                            if (!isset($existingNoSet[strtoupper($candidate)])) {
                                $no_pp = $candidate;
                                $generated = true;
                            }
                        }

                        if (!$generated) {
                            $no_pp = "{$bulanTahun}.{$div_kode}.{$kodeJenis}.{$tgl}." . time();
                        }
                    } else {
                        if (isset($existingNoSet[strtoupper($no_pp)])) {
                            $skipped[] = $no_pp . ' (duplikat dari CSV)';
                            continue;
                        }
                    }

                    $existingNoSet[strtoupper($no_pp)] = true;

                    $pp = PaymentPlan::create([
                        'no_transaksi'     => strtoupper($no_pp),
                        'id_divisi'        => $id_divisi,
                        'id_akun'          => !empty($detil_akun) ? $detil_akun : null,
                        'tgl_pengajuan'    => $parsedDate,
                        'tgl_transaksi'    => $parsedDate,
                        'jatuh_tempo'      => null,
                        'jenis_transaksi'  => strtoupper($rekening_ops),
                        'kategori_payment' => strtoupper($kategori),
                        'vendor_toko'      => strtoupper($vendor),
                        'nama_toko_link'   => $nama_toko_link,
                        'penerima_pj'      => strtoupper($nama_pj),
                        'rekening_va'      => strtoupper($rekening_va),
                        'keterangan'       => $keterangan,
                        'nominal'          => 0,
                        'status_payment'   => $status,
                    ]);

                    $pp->details()->create([
                        'nama_item'      => null,
                        'qty'            => $qty,
                        'satuan'         => $satuan,
                        'keterangan'     => $keterangan,
                        'nominal'        => $nominal,
                        'nominal_aktual' => $nominal_aktual,
                    ]);

                    $pp->recalcFromDetails();
                    $inserted++;

                } catch (\Throwable $e) {
                    $failed++;
                    if (count($errorMessages) < 5) {
                        $errorMessages[] = "[BARIS " . ($inserted + $failed + 1) . "]: " . explode(' (Connection', $e->getMessage())[0];
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses file CSV: ' . $e->getMessage());
        }

        fclose($handle);
        SystemLog::record('IMPORT', 'Payment Plan', 'Import payment plan berhasil. ' . $inserted . ' data terekam.');

        if ($failed > 0) {
            $msg = "PERHATIAN! Berhasil: $inserted data. GAGAL: $failed data. ALASAN DITOLAK: " . implode(' | ', $errorMessages);
            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('success', "SEMPURNA! $inserted data Payment Plan berhasil diimpor semua.");
    }

    public function publicForm()
    {
        $divisi = MasterDivisi::where('status_aktif', 1)->orderBy('nama_divisi', 'asc')->get();
        return view('payment_plan.public_form', compact('divisi'));
    }

    public function publicStore(Request $request)
    {
        $request->validate([
            'id_divisi' => 'required',
            'tgl_pengajuan' => 'required|date',
            'tgl_transaksi' => 'nullable|date',
            'kategori_payment' => 'required',
            'vendor_toko' => 'required',
            'penerima_pj' => 'required',
            'keterangan' => 'required',
            'nominal' => 'required|numeric|min:0',
            'bukti_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'pin_perusahaan' => 'required|numeric'
        ]);

        $dbPin = \App\Models\CompanyProfile::first()?->employee_pin;
        if (!$dbPin || (string) $request->pin_perusahaan !== (string) $dbPin) {
            return redirect()->back()->withInput()->with('error', 'PIN otorisasi salah atau belum diatur.');
        }

        DB::beginTransaction();

        try {
            $tgl_pengajuan = $request->tgl_pengajuan;
            $no_transaksi = $this->generateNoTransaksi($request->id_divisi, $tgl_pengajuan, 'KAS');

            $filePath = null;
            if ($request->hasFile('bukti_file')) {
                $file = $request->file('bukti_file');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '', $file->getClientOriginalName());
                $filePath = $file->storeAs('bukti_payment', $filename, 'public');
            }

            $pp = PaymentPlan::create([
                'no_transaksi'     => strtoupper($no_pp),
                'id_divisi'        => $id_divisi,
                'id_akun'          => !empty($detil_akun) ? $detil_akun : null,
                'tgl_pengajuan'    => $parsedDate,
                'tgl_transaksi'    => $parsedDate,
                'jatuh_tempo'      => null,
                'jenis_transaksi'  => strtoupper($rekening_ops),
                'kategori_payment' => strtoupper($kategori),
                'vendor_toko'      => strtoupper($vendor),
                'nama_toko_link'   => $nama_toko_link,
                'penerima_pj'      => strtoupper($nama_pj),
                'rekening_va'      => strtoupper($rekening_va),
                'keterangan'       => $keterangan,
                'nominal'          => 0,
                'status_payment'   => $status,
            ]);

            $pp->details()->create([
                'nama_item'      => null,
                'qty'            => $qty,
                'satuan'         => $satuan,
                'keterangan'     => $keterangan,
                'nominal'        => $nominal,
                'nominal_aktual' => $nominal_aktual,
            ]);

            $pp->recalcFromDetails();
            $inserted++;

            DB::commit();
            SystemLog::record('CREATE', 'Payment Plan', 'Pengajuan baru dari portal karyawan: ' . $no_transaksi . ' - ' . ($request->penerima_pj ?? '-'));

            return redirect()->back()->with('success', "Pengajuan Berhasil Terkirim! Nomor Tiket Anda: {$no_transaksi}. Silakan konfirmasi ke bagian Finance.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal memproses pengajuan: ' . $e->getMessage());
        }
    }

    public function setRekening(Request $request, $id)
    {
        $request->validate(['jenis_transaksi' => 'required|string']);

        $current = PaymentPlan::where('id_payment', $id)->first();
        if (!$current) {
            return redirect()->back()->with('error', 'Data tidak ditemukan!');
        }

        $current->update([
            'jenis_transaksi' => strtoupper($request->jenis_transaksi),
            'updated_at' => now()
        ]);

        SystemLog::record('UPDATE', 'Payment Plan', 'Menetapkan Rekening Sumber Dana: ' . $current->no_transaksi);

        return redirect()->back()->with('success', 'Rekening Sumber Dana berhasil diperbarui oleh Keuangan!');
    }

    public function apiAccounts(Request $request)
    {
        $q = trim($request->input('q', ''));
        $accounts = Account::when($q, function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('account_code', 'like', "%{$q}%")
                        ->orWhere('account_name', 'like', "%{$q}%");
                });
            })
            ->orderBy('account_code', 'asc')
            ->limit(100)
            ->get(['account_code', 'account_name']);

        return response()->json($accounts);
    }

    /**
     * API: Search vendors (for cascading dropdown step 1)
     * Fetches from HelperCode (Kode Bantu), PurchaseBill (contact_name), and PurchaseOrder (contact_name).
     * NOTE: purchase_bills table uses 'contact_name' column (not 'vendor_name').
     */
    public function apiSearchVendors(Request $request)
    {
        $q = trim($request->input('q', ''));

        // 1. Get from Kode Bantu (HelperCode)
        $helperVendors = \App\Models\HelperCode::when($q !== '', function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('entity_name', 'like', "%{$q}%")
                        ->orWhere('helper_code', 'like', "%{$q}%")
                        ->orWhere('marketing_name', 'like', "%{$q}%");
                });
            })
            ->select('entity_name')
            ->distinct()
            ->limit(30)
            ->pluck('entity_name');

        // 2. Get from PurchaseBill - column is 'contact_name', not 'vendor_name'
        $billVendors = \App\Models\PurchaseBill::when($q !== '', function($query) use ($q) {
                $query->where('contact_name', 'like', "%{$q}%");
            })
            ->select('contact_name')
            ->distinct()
            ->limit(20)
            ->pluck('contact_name');

        // 3. Get from PurchaseOrder
        $poVendors = \App\Models\PurchaseOrder::when($q !== '', function($query) use ($q) {
                $query->where('contact_name', 'like', "%{$q}%");
            })
            ->select('contact_name')
            ->distinct()
            ->limit(20)
            ->pluck('contact_name');

        $vendors = $helperVendors->merge($billVendors)->merge($poVendors)->filter()->unique()->sort()->values()->toArray();

        return response()->json(collect($vendors)->map(function ($v) {
            return ['id' => $v, 'text' => $v];
        }));
    }

    /**
     * API: Get purchase bills by vendor (for cascading dropdown)
     * Used by Pembayaran Hutang flow
     * NOTE: purchase_bills uses 'contact_name' and 'transaction_date' columns.
     */
    public function apiBillsByVendor(Request $request)
    {
        $vendorName = trim($request->input('vendor_name', ''));
        $q = trim($request->input('term', $request->input('q', '')));
        
        // purchase_bills table: contact_name (not vendor_name), transaction_date (not bill_date)
        $query = \App\Models\PurchaseBill::where('payment_status', 'UNPAID');

        if ($vendorName !== '') {
            $query->where('contact_name', 'like', "%{$vendorName}%");
        }

        if ($q !== '') {
            $query->where('bill_number', 'like', "%{$q}%");
        }

        $bills = $query->orderBy('transaction_date', 'desc')
            ->limit(50)
            ->get(['bill_number', 'transaction_date', 'grand_total', 'contact_name']);

        return response()->json($bills->map(function ($bill) {
            return [
                'id'          => $bill->bill_number,
                'text'        => $bill->bill_number . ' | ' . date('d/m/Y', strtotime($bill->transaction_date)) . ' | ' . $bill->contact_name . ' | Rp ' . number_format($bill->grand_total, 0, ',', '.'),
                'grand_total' => (int) $bill->grand_total,
            ];
        }));
    }

    /**
     * API: Get purchase orders by vendor (for cascading dropdown)
     * Used by Uang Muka Pembelian flow
     */
    public function apiPOsByVendor(Request $request)
    {
        $vendorName = trim($request->input('vendor_name', ''));
        $q = trim($request->input('term', $request->input('q', '')));

        $query = \App\Models\PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIAL', 'RECEIVED']);

        if ($vendorName !== '') {
            $query->where('contact_name', 'like', "%{$vendorName}%");
        }

        if ($q !== '') {
            $query->where('po_number', 'like', "%{$q}%");
        }

        $pos = $query->orderBy('transaction_date', 'desc')
            ->limit(50)
            ->get(['po_number', 'transaction_date', 'grand_total', 'status']);

        return response()->json($pos->map(function ($po) {
            return [
                'id' => $po->po_number,
                'text' => $po->po_number . ' | ' . date('d/m/Y', strtotime($po->transaction_date)) . ' | Rp ' . number_format($po->grand_total, 0, ',', '.') . ' (' . $po->status . ')',
                'grand_total' => (int) $po->grand_total,
            ];
        }));
    }

    /**
     * Query dasar untuk export ke Jubelio.
     * Mode cepat (default, all=0): HANYA status PENGAJUAN, mengabaikan filter status lain.
     * Mode custom (all=1): pakai seluruh filter dari modal (tanggal, divisi, kategori, status).
     */
    private function resolveExportBaseQuery(Request $request)
    {
        $query = PaymentPlan::with(['divisi', 'account', 'paymentCategory']);

        if (!$request->boolean('all')) {
            return $query->where('status_payment', 'PENGAJUAN')->orderBy('tgl_pengajuan');
        }

        $query->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $q->whereBetween('tgl_pengajuan', [$request->start_date, $request->end_date]);
            })
            ->when($request->filled('id_divisi'), function ($q) use ($request) {
                $q->where('id_divisi', $request->id_divisi);
            })
            ->when($request->filled('kategori_payment'), function ($q) use ($request) {
                $q->where('kategori_payment', $request->kategori_payment);
            })
            ->when($request->filled('status_payment'), function ($q) use ($request) {
                $q->where('status_payment', $request->status_payment);
            });

        return $query->orderBy('tgl_pengajuan');
    }

    public function exportJubelioKasBank(Request $request)
    {
        $manualHutangCategories = [
            'PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)',
            'PEMBELIAN PERSEDIAAN (UANG MUKA)',
            'DEPOSIT',
        ];

        $rows = $this->resolveExportBaseQuery($request)
            ->where(function ($q) use ($manualHutangCategories) {
                $q->whereHas('paymentCategory', function ($sub) {
                    $sub->where('jubelio_flow', 'KAS_BANK');
                })
                ->orWhereNotIn('kategori_payment', $manualHutangCategories);
            })
            ->get();

        if ($rows->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data kategori Kas & Bank yang bisa diekspor sesuai filter (pastikan status PENGAJUAN tersedia atau gunakan filter custom).');
        }

        SystemLog::record('EXPORT', 'Payment Plan', 'Export Kas & Bank Jubelio: ' . $rows->count() . ' data.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PaymentPlanKasBankJubelioExport($rows),
            'Jubelio_KasBank_Import_' . date('Ymd_His') . '.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportManualWorklist(Request $request)
    {
        $rows = $this->resolveExportBaseQuery($request)
            ->whereHas('paymentCategory', function ($q) {
                $q->where('jubelio_flow', 'MANUAL_HUTANG');
            })
            ->get();

        if ($rows->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data kategori Hutang/Uang Muka/Deposit yang bisa diekspor sesuai filter.');
        }

        SystemLog::record('EXPORT', 'Payment Plan', 'Export Worklist Manual Hutang: ' . $rows->count() . ' data.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PaymentPlanManualWorklistExport($rows),
            'Worklist_Manual_Hutang_' . date('Ymd_His') . '.xlsx'
        );
    }
}

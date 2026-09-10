# ERP Akuntansi Manufaktur — Integrasi Modul Manufaktur

> **Status: Modul Manufaktur (MFG/SPK) SELESAI dan menjadi satu kesatuan utuh dengan
> ERP Akuntansi.** Anthrilo (Python/FastAPI) **tidak lagi dibutuhkan** — seluruh logic,
> struktur data, dan workflow-nya sudah diterjemahkan penuh menjadi native Laravel di
> dalam aplikasi ini. Tidak ada dependensi runtime apa pun ke Anthrilo.

## 0. Klarifikasi Penting: Ini Port Penuh, Bukan Bridge

Supaya tidak ada salah paham (isu ini sempat muncul di tengah pengerjaan):

- **Sumber desain/logic/struktur/workflow**: 100% dari Anthrilo (`db/models.py`,
  `alembic/versions/004_manufacturing.py`) — tabel `mfg_*`, alur produksi
  (Knitting → Processing → Cutting → Stitching → Finishing → Barcode), field-field
  seperti `marker_efficiency`, `shrinkage_percent`, `fabric_wastage_kg`, `size_breakdown`.
- **Sumber konvensi kode**: dari proyek akuntansi Anda sendiri (`RULES.md`,
  `PurchaseOrderService` sebagai blueprint pola Service/Controller). `RULES.md` **tidak**
  menyebut Anthrilo sama sekali — ia hanya menyediakan prefix placeholder `MFG`/`SPK` yang
  belum diimplementasikan. Jadi kesimpulan "Anthrilo tetap dipakai berdampingan" bukan
  berasal dari isi `RULES.md`, dan memang **bukan** itu yang dibangun di sini.
- **Yang sempat berpotensi disalahpahami sebagai "bridge"**: command CSV import
  `import:anthrilo-master` / `import:anthrilo-opening-balance` dari iterasi sebelumnya.
  Command itu **hanya migrasi data historis satu kali**, bukan sinkronisasi berjalan —
  tapi karena membingungkan dan Anthrilo memang akan dipensiunkan total, **command
  tersebut sudah DIHAPUS** dari paket ini. Data di sistem baru dimulai dari nol lewat
  Master Data (Yarn/Fabric/Supplier/Rate Proses) atau, jika Anda tetap ingin membawa data
  lama, lewat fitur **Import Excel** yang kini tersedia di setiap menu (lihat §6).

Semua tabel, model, service, controller, view berjalan native di dalam Laravel — start
dari sini, Anthrilo boleh dimatikan kapan saja.

## 1. Pemetaan Tabel: Anthrilo → Akuntansi

| Anthrilo (Python/SQLAlchemy) | Akuntansi (Laravel, baru) | Catatan |
|---|---|---|
| `suppliers` | `mfg_suppliers` | + kolom `helper_code` → tersambung ke `helper_codes` (subledger AP) |
| `yarns` | `mfg_yarns` | + `average_cost`, `inventory_account_code` (moving average) |
| `fabrics` | `mfg_fabrics` | + kolom `state` (GREY/FINISHED) menyatukan 2 konsep Anthrilo dalam 1 tabel |
| `processes` | `mfg_processes` | rate jasa vendor (knitting/dyeing/printing/finishing/cutting/stitching) |
| `purchase_orders` + `po_items` (raw material) | `mfg_material_purchase_orders` + `mfg_material_purchase_order_details` | dipisah dari `purchase_orders` inti akuntansi (barang jadi/[External Platform]) |
| `gate_entries` + `mrns` + `mrn_items` | `mfg_material_receipts` + `mfg_material_receipt_details` | digabung jadi 1 dokumen MRN |
| `inventory_transactions` | `mfg_material_ledgers` | versi Laravel, moving average khusus bahan baku |
| `knit_orders` | `mfg_knit_orders` | + `work_order_id` (link ke SPK, opsional/traceability) |
| `yarn_issues_to_knitter` + `yarn_issue_items` | `mfg_yarn_issues` | disederhanakan 1 tabel |
| `grey_fabric_receipts` | `mfg_grey_fabric_receipts` | + `knitting_cost_amount` |
| `processing_orders` | `mfg_processing_orders` | + `work_order_id` |
| `grey_fabric_issues` | `mfg_fabric_issues` | + `unit_cost`/`total_cost` |
| `finished_fabric_receipts` | `mfg_fabric_receipts` | + `process_cost_amount` |
| `cutting_orders` | `mfg_cutting_orders` | + `fabric_unit_cost`/`fabric_total_cost` |
| `cutting_checks` | `mfg_cutting_checks` | + `wastage_cost_amount` (nilai rupiah, bukan cuma kg) |
| `stitching_orders` | `mfg_stitching_orders` | + `total_stitching_cost` |
| `garment_finishing` | `mfg_finishing_stages` | struktur sama (stage: WASHING/IRONING/QC/PACKING) |
| `barcode_labels` | `mfg_barcode_labels` | + `product_id` (link ke `products`) |
| *(tidak ada)* | `mfg_work_orders` | **BARU** — header SPK, menyatukan rantai produksi 1 batch/style + akumulasi biaya WIP |

Domain e-commerce/sales Anthrilo (`sales`, `panels`, `discounts`, `paid_ads`, `ads_data`)
**sengaja tidak diporting** — di luar cakupan "Manufacturing → WIP & Biaya Produksi".

## 2. Alur Produksi & Titik Jurnal

> **Prinsip penting**: tahap Knitting & Processing **tidak** menyentuh akun WIP — kain
> grey/finished hasil kedua tahap itu masih stok umum (fungible), belum terikat ke SPK
> tertentu. **WIP baru dimulai di tahap Cutting**, saat kain resmi dipotong untuk 1
> SPK/style spesifik dan tidak bisa lagi dialihkan ke style lain. Ini konvensi akuntansi
> biaya garmen standar, diterapkan DI ATAS alur kerja Anthrilo (Anthrilo sendiri tidak
> punya konsep akuntansi sama sekali).

```
Supplier bahan baku
   │  PO (mfg_material_purchase_orders)
   ▼
MRN diterima ──────────────► JURNAL #1: Debit Persediaan Bahan Baku (Yarn/Fabric)
(mfg_material_receipts)         Kredit Hutang Usaha Maklun (+ Debit Pajak Masukan jika ada PPN)
   │  MaterialReceiptService::createAndPost()
   ▼
Knit Order: Yarn Issue ──────► (tidak jurnal — perpindahan internal gudang→vendor)
   │  KnitOrderService::issueYarn()
   ▼
Grey Fabric Receipt ──────────► JURNAL #2: Debit Persediaan Bahan Baku Kain (yarn cost + biaya knitting)
(mfg_grey_fabric_receipts)         Kredit Persediaan Bahan Baku Benang (yarn terpakai)
   │  KnitOrderService::receiveGreyFabric()  Kredit Hutang Usaha Maklun (biaya jasa knitting)
   ▼
Fabric Issue ke Processor ────► (tidak jurnal — perpindahan internal)
   │  ProcessingOrderService::issueFabric()
   ▼
Fabric Receipt (finished) ────► JURNAL #3: Debit Persediaan Bahan Baku Kain (finished, grey cost + proses)
(mfg_fabric_receipts)              Kredit Persediaan Bahan Baku Kain (grey terpakai)
   │  ProcessingOrderService::receiveFabric()  Kredit Hutang Usaha Maklun (biaya jasa proses)
   ▼
═══════════ TITIK AWAL WIP (kain resmi terikat ke 1 SPK) ═══════════
Cutting Order dibuat ──────────► JURNAL #4: Debit WIP Produksi (fabric_total_cost)
(mfg_cutting_orders)                Kredit Persediaan Bahan Baku Kain
   │  CuttingOrderService::create()   + WorkOrderService::accumulateCost(materialCost)
   ▼
Cutting Check (QC + wastage) ──► JURNAL #4b (HANYA jika ada wastage):
(mfg_cutting_checks)                Debit Kerugian Wastage Produksi (fabric_wastage_kg × fabric_unit_cost)
   │  CuttingOrderService::recordCheck()  Kredit WIP Produksi
   ▼
Stitching Order dibuat ────────► JURNAL #5: Debit WIP Produksi (pieces_issued × stitching_rate)
(mfg_stitching_orders)              Kredit Hutang Usaha Maklun (vendor CMT)
   │  StitchingOrderService::create()  + WorkOrderService::accumulateCost(processCost)
   ▼
Finishing Stages (washing/ironing/QC/packing) ─► tidak jurnal, murni tracking pieces
   │  FinishingStageService::record()
   ▼
SPK diselesaikan (status=COMPLETED) ──► JURNAL #6: Debit Persediaan Barang Jadi (total_wip_cost)
(mfg_work_orders)                          Kredit WIP Produksi
   │  WorkOrderService::complete()   + update products.average_cost (moving average)
   ▼
Barcode Label dicetak (BarcodeLabelService::generate) — tidak jurnal, identitas fisik saja
```

**Validasi Double-Entry (RULES.md §2)**: setiap Jurnal #1–#6 di atas kini dicek dengan
`JournalBalanceValidator::isBalanced()` **sebelum** `JournalDetail::insert()` — jika Debet
≠ Kredit, exception dilempar dan `DB::transaction()` otomatis rollback. Ini pengaman
tambahan yang ditambahkan di Stage 6 (sebelumnya jurnal dibangun manual by construction
tanpa pengecekan eksplisit).

## 3. COA (`config/coa.php`)

| Key | Default Akun | Fungsi |
|---|---|---|
| `persediaan_bahan_baku_benang` | 11210 | Debit saat MRN yarn, Kredit saat yarn issue ke knitter |
| `persediaan_bahan_baku_kain` | 11220 | Debit saat MRN fabric / hasil knitting / processing, Kredit saat cutting |
| `wip_produksi` | 11500 | Akumulasi biaya produksi berjalan (mulai Cutting sampai SPK selesai) |
| `persediaan_barang_jadi` | = 11200 (`persediaan`) | Reuse akun Persediaan yang sudah ada |
| `hutang_usaha_maklun` | 22010 | Subledger AP jasa vendor (knitting/dyeing/CMT) |
| `kerugian_wastage_produksi` | 88005 | Kain terbuang saat cutting (TIDAK dikapitalisasi ke WIP) |
| `selisih_produksi` | 88006 | Varians rencana vs aktual (cadangan, belum dipakai otomatis) |

## 4. Prefix Dokumen (update §8.1 RULES.md)

| KEY | Tabel | Kolom | Format |
|---|---|---|---|
| `MFG` / `SPK` | `mfg_work_orders` | `spk_number` | `MFG-YYYYMMDD-####` |
| `MRN` | `mfg_material_receipts` | `receipt_number` | `MRN-YYYYMMDD-####` |

Keduanya sudah aktif di `DocumentTraceController::trace()` (sebelumnya placeholder kosong).

## 5. Struktur File Lengkap

```
database/migrations/2026_08_15_090001..090021_*.php   — 21 migration tabel mfg_*
database/migrations/2026_08_20_100001_*.php            — tambah status VOIDED di mfg_material_receipts

app/Modules/Manufacturing/Models/*.php                  — 21 Eloquent model
app/Modules/Manufacturing/Support/MaterialCostHelper.php — moving average yarn/fabric (shared)
app/Modules/Manufacturing/Services/*.php                — 8 Service (posting jurnal + void)
app/Modules/Manufacturing/Http/Controllers/*.php        — 13 Controller (10 transaksi + report)
app/Modules/Manufacturing/Exports/*.php                 — 8 Export (Excel)
app/Modules/Manufacturing/Imports/*.php                 — 5 Import (Excel/CSV)

routes/manufacturing.php   — SEMUA rute mfg.*, di-require dari web.php
config/coa.php                                    — MODIFIKASI (+7 key manufaktur)
app/Http/Controllers/DocumentTraceController.php  — MODIFIKASI (aktifkan MFG/SPK/MRN)
resources/views/layouts/app.blade.php             — MODIFIKASI (+menu sidebar Manufaktur)

resources/views/manufacturing/work_order/*.blade.php        — index, create, show (hub utama)
resources/views/manufacturing/material_receipt/*.blade.php  — index, create, show
resources/views/manufacturing/master/*.blade.php             — yarn, fabric, supplier, process
resources/views/manufacturing/report/hpp.blade.php           — Laporan HPP Manufaktur
```

## 6. Fitur Import / Export / Print per Menu

Selain input manual (dipertahankan penuh, tidak ada yang dihapus), setiap menu berikut
kini punya:

| Menu | Import (Excel/CSV) | Export (Excel) | Print |
|---|---|---|---|
| Master Yarn | upsert by kode (stok/HPP TIDAK ikut — hanya berubah lewat MRN) | ya | - |
| Master Fabric | upsert by kode | ya | - |
| Master Supplier | upsert by kode | ya | - |
| Master Rate Proses | insert baru (tak ada kode unik) | ya | - |
| Material Receipt (MRN) | import transaksional — tiap baris tetap lewat `MaterialReceiptService::createAndPost()`, jurnal & validasi identik dgn input manual | ya (daftar) | ya per dokumen (`window.print()` + CSS `@media print`, pola sama dgn `profit-loss.blade.php`) |
| SPK (Work Order) | - (SPK adalah proses bertahap, tidak masuk akal diimpor massal) | ya (daftar) | ya per SPK (trace produksi lengkap) |
| Laporan HPP Manufaktur | - | ya | ya |

Pola Export mengikuti persis `ProductController` (query param `?export=excel` pada route
`index` yang sama, bukan route terpisah). Pola Import mengikuti persis `HelperCodeImport`
+ `HelperCodeController::import()` (modal upload + link "Download Template" CSV).

**Kenapa MRN import BEDA dari import master data**: baris MRN adalah transaksi
(harus memicu jurnal & kartu stok yang benar), bukan sekadar data — jadi
`MaterialReceiptImport` mengelompokkan baris per "REF SEMENTARA" lalu memanggil Service
yang SAMA dengan form manual, bukan insert langsung ke tabel. Ini memastikan tidak ada
jalur pintas yang melewati validasi/jurnal.

**Kenapa Print pakai `window.print()`, bukan PDF server-side**: proyek Anda tidak
memasang library PDF (dompdf/tcpdf) di `composer.json` — pola cetak yang SUDAH ADA
(`profit-loss.blade.php`) memakai CSS `@media print` + tombol `window.print()`, jadi
diikuti persis, bukan menambahkan dependency baru.

## 7. Fitur Void (SEMUA TAHAP)

Setiap tahap produksi kini bisa di-void, mengikuti pola persis `PurchaseOrderService::voidReceipt()`
(hapus jurnal by `evidence_number`, balik kembali stok/`average_cost`, hapus baris kartu stok) —
dan masing-masing punya **pengecekan ketergantungan tahap berikutnya** sebelum mengizinkan void,
supaya tidak ada data yang jadi tidak konsisten:

| Tahap | Method | Syarat boleh di-void |
|---|---|---|
| MRN | `MaterialReceiptService::void()` | Stok item MRN belum terpakai di transaksi lain |
| Yarn Issue | `KnitOrderService::voidYarnIssue()` | Knit Order belum menerima kain grey (status masih `ISSUED`) |
| Grey Fabric Receipt | `KnitOrderService::voidGreyFabricReceipt()` | Kain grey belum terpakai (diissue ke processor/dipotong) |
| Fabric Issue (ke processor) | `ProcessingOrderService::voidFabricIssue()` | Processing Order belum menerima kain finished (status masih `ISSUED`) |
| Fabric Receipt (finished) | `ProcessingOrderService::voidFabricReceipt()` | Kain finished belum terpakai (belum dipotong) |
| Cutting Order | `CuttingOrderService::void()` | Status masih `OPEN` (belum ada QC) dan belum ada Stitching Order turunan |
| Cutting Check (QC/wastage) | `CuttingOrderService::voidCheck()` | Belum ada Stitching Order turunan dari Cutting Order ini |
| Stitching Order | `StitchingOrderService::void()` | Belum ada Finishing Stage tercatat |
| Penyelesaian SPK | `WorkOrderService::voidCompletion()` | Stok barang jadi hasil SPK ini belum terjual/terpakai |

Semua tombol Void muncul otomatis di halaman **detail SPK** (`work_order/show.blade.php`) hanya
saat kondisi di atas terpenuhi (tombol tidak ditampilkan jika syarat tidak terpenuhi — mencegah
klik yang pasti gagal), dengan konfirmasi `confirm()` sebelum submit. Void yang gagal karena
syarat tidak terpenuhi akan menampilkan pesan error yang jelas (bukan silent fail).

**Tidak ada void untuk**: Finishing Stage (tracking fisik, tidak berjurnal — tidak perlu void,
cukup catat ulang tahap berikutnya jika salah) dan Barcode Label (identitas fisik, hapus manual
dari database jika perlu, tidak memengaruhi akuntansi).

## 8. Cara Pasang

1. `php artisan migrate` (21+1 migration).
2. Timpa (bandingkan dulu) 3 file yang DIMODIFIKASI: `config/coa.php`,
   `app/Http/Controllers/DocumentTraceController.php`, `resources/views/layouts/app.blade.php`.
3. Tambah 1 baris di `routes/web.php` (di dalam grup `Route::middleware(['auth'])`):
   ```php
   require base_path('routes/manufacturing.php');
   ```
4. Buka menu sidebar **Manufaktur** → isi Master Data (Supplier/Yarn/Fabric/Rate Proses)
   dulu — bisa manual satu-satu, atau **Import Excel** jika sudah punya data dalam jumlah
   banyak (termasuk dari ekspor Anthrilo sebelum dimatikan, tinggal disesuaikan ke format
   template masing-masing menu).
5. Mulai transaksi dari **Material Receipt (MRN)**, lalu **SPK** untuk seluruh alur
   produksi.

## 9. Ringkasan Status

| Bagian | Isi | Status |
|---|---|---|
| Database | 21 migration + 21 model | selesai |
| Service & Jurnal | 8 Service, 6 titik jurnal + validasi balance | selesai |
| UI Transaksi | 13 Controller + 11 View + sidebar | selesai |
| Import/Export/Print | 8 Export, 5 Import, print di MRN/SPK/Laporan | selesai |
| Fitur Void | Semua 9 titik transaksi (MRN s/d Penyelesaian SPK) | selesai |
| Laporan | HPP Manufaktur per periode | selesai |
| Ketergantungan Anthrilo | tidak ada — sistem berjalan mandiri | selesai |

Semua logic/struktur/workflow bersumber dari Anthrilo; semua konvensi kode/penamaan
mengikuti proyek akuntansi Anda. `RULES.md` hanya referensi konvensi, bukan sumber desain
manufaktur, dan tidak pernah menyiratkan Anthrilo harus tetap berjalan.

## 10. Multi-Bahasa (i18n) untuk Modul Manufaktur

> **Status: [BARU]** — Modul Manufaktur (MFG/SPK) sekarang fully i18n-ready.
> Mengikuti prinsip §10 RULES.md: "Data Baku, UI Fleksibel".

### 10.1 Apa yang TIDAK Diterjemahkan (Tetap Baku)

| Item | Nilai di DB | Alasan |
|---|---|---|
| `mfg_work_orders.status` | `OPEN`, `IN_PROGRESS`, `COMPLETED`, `VOIDED` | Enum, logika bisnis |
| `mfg_processes.process_type` | `KNITTING`, `DYEING`, `PRINTING`, `FINISHING`, `CUTTING`, `STITCHING`, `OTHER` | Enum master data |
| `mfg_fabrics.state` | `GREY`, `FINISHED` | Enum master data |
| `mfg_finishing_stages.stage` | `WASHING`, `IRONING`, `QC`, `PACKING`, `OTHER` | Enum |
| `mfg_material_ledgers.item_type` | `YARN`, `FABRIC` | Enum |
| `mfg_material_ledgers.type` | `IN`, `OUT` | Enum |
| Prefix dokumen | `MFG-`, `MRN-`, `CO-`, `SEW-`, `FI-`, `PRC-`, `GFR-` | Audit trail |
| COA codes | `11210`, `11220`, `11500`, `21110`, `88006`, dll | Standar akuntansi |
| Nama akun di `accounts` | 'Persediaan Bahan Baku - Yarn', 'Hutang Usaha Maklun', dll | Data master |

### 10.2 Apa yang WAJIB Diterjemahkan (UI Layer)

#### A. Label Status di Blade (pakai helper)

Buat helper `App\Support\ManufacturingLabel` untuk sentralisasi:

```php
<?php
namespace App\Support;

class ManufacturingLabel
{
    /**
     * Terjemahkan status SPK ke label UI multi-bahasa.
     * DB: 'COMPLETED' → ID: 'Selesai' / EN: 'Completed' / ZH: '已完成'
     */
    public static function workOrderStatus(string $status): string
    {
        return match(strtoupper($status)) {
            'OPEN' => __('erp.mfg_status_open'),
            'IN_PROGRESS' => __('erp.mfg_status_in_progress'),
            'COMPLETED' => __('erp.mfg_status_completed'),
            'VOIDED' => __('erp.mfg_status_voided'),
            default => $status, // fallback aman
        };
    }

    public static function processType(string $type): string
    {
        return match(strtoupper($type)) {
            'KNITTING' => __('erp.mfg_process_knitting'),
            'DYEING' => __('erp.mfg_process_dyeing'),
            'PRINTING' => __('erp.mfg_process_printing'),
            'FINISHING' => __('erp.mfg_process_finishing'),
            'CUTTING' => __('erp.mfg_process_cutting'),
            'STITCHING' => __('erp.mfg_process_stitching'),
            'OTHER' => __('erp.mfg_process_other'),
            default => $type,
        };
    }

    public static function fabricState(string $state): string
    {
        return match(strtoupper($state)) {
            'GREY' => __('erp.mfg_fabric_grey'),
            'FINISHED' => __('erp.mfg_fabric_finished'),
            default => $state,
        };
    }

    public static function finishingStage(string $stage): string
    {
        return match(strtoupper($stage)) {
            'WASHING' => __('erp.mfg_stage_washing'),
            'IRONING' => __('erp.mfg_stage_ironing'),
            'QC' => __('erp.mfg_stage_qc'),
            'PACKING' => __('erp.mfg_stage_packing'),
            'OTHER' => __('erp.mfg_stage_other'),
            default => $stage,
        };
    }
}
```

**Pemakaian di Blade:**
```blade
{{-- ❌ SALAH (hardcoded) --}}
<td>{{ $order->status }}</td>
<td class="badge bg-{{ $order->status === 'COMPLETED' ? 'success' : 'warning' }}">
    {{ $order->status === 'COMPLETED' ? 'Selesai' : 'Dalam Proses' }}
</td>

{{-- ✅ BENAR (i18n-ready) --}}
<td>{{ \App\Support\ManufacturingLabel::workOrderStatus($order->status) }}</td>
<td class="badge bg-{{ $order->status === 'COMPLETED' ? 'success' : 'warning' }}">
    {{ \App\Support\ManufacturingLabel::workOrderStatus($order->status) }}
</td>
```

#### B. Flash Messages di Controller Manufaktur

**Semua controller di `App\Modules\Manufacturing\Http\Controllers` WAJIB i18n:**

```php
// ✅ BENAR — ProcessingOrderController.php
public function store(Request $request)
{
    $request->validate([
        'work_order_id' => 'required|exists:mfg_work_orders,id',
        'supplier_id' => 'required|exists:mfg_suppliers,id',
        'target_date' => 'required|date|after_or_equal:today',
    ], [
        // Validation messages otomatis i18n dari lang/{locale}/validation.php
        // Tapi custom message tetap bisa di-override:
        'target_date.after_or_equal' => __('erp.mfg_target_date_must_future'),
    ]);

    try {
        $order = $this->service->create($request->validated());
        SystemLog::record('CREATE', 'Manufacturing Processing Order',
            'Membuat Processing Order: ' . $order->order_number); // Log TETAP ID

        return redirect()->route('mfg.work-orders.show', $request->work_order_id)
            ->with('success', __('erp.mfg_processing_order_created', [
                'number' => $order->order_number
            ]));
    } catch (\Exception $e) {
        \Log::error('ProcessingOrder creation failed: ' . $e->getMessage());
        return redirect()->back()
            ->withInput()
            ->with('error', __('erp.mfg_processing_order_failed', [
                'reason' => $this->mapError($e->getMessage())
            ]));
    }
}

public function issueFabric(Request $request, $id)
{
    // ...
    if ($fabric->stock_quantity < $request->qty_issued) {
        return redirect()->back()
            ->with('error', __('erp.mfg_fabric_stock_insufficient', [
                'available' => $fabric->stock_quantity,
                'requested' => $request->qty_issued,
                'fabric_code' => $fabric->fabric_code,
            ]));
    }
    // ...
}
```

#### C. Breadcrumb & Page Title

```blade
{{-- ❌ SALAH --}}
<x-breadcrumb :links="['Manufaktur' => '#', 'Material Receipt (MRN)' => route('mfg.material-receipts.index'), 'Buat Baru' => null]" />

{{-- ✅ BENAR --}}
<x-breadcrumb :links="[
    __('erp.mfg_module') => '#',
    __('erp.mfg_material_receipt') => route('mfg.material-receipts.index'),
    __('erp.create_new') => null,
]" />
```

#### D. Export Excel Headers

```php
// ManufacturingProcessExport.php
public function headings(): array
{
    return [
        __('erp.mfg_process_name'),
        __('erp.mfg_process_type'),
        __('erp.mfg_rate_unit'),
        __('erp.mfg_process_rate'),
        __('erp.status'),
    ];
}
```

#### E. Alert/Notification Messages

```php
// Service layer — pesan error yang akan ditampilkan ke user
if (!$workOrder) {
    throw new \Exception(__('erp.mfg_work_order_not_found', ['id' => $id]));
}

if ($workOrder->status === 'COMPLETED') {
    throw new \Exception(__('erp.mfg_work_order_already_completed', [
        'number' => $workOrder->spk_number
    ]));
}
```

### 10.3 Keys i18n Wajib untuk Modul Manufaktur

Tambahkan di `lang/id/erp.php`, `lang/en/erp.php`, `lang/zh_CN/erp.php`:

```php
// === MANUFACTURING MODULE (MFG) ===
'mfg_module' => 'Manufaktur',
'mfg_work_orders' => 'Surat Perintah Kerja (SPK)',
'mfg_material_receipt' => 'Penerimaan Bahan (MRN)',
'mfg_master_yarn' => 'Master Benang',
'mfg_master_fabric' => 'Master Kain',
'mfg_master_supplier' => 'Master Supplier/Vendor',
'mfg_master_process' => 'Master Rate Proses',
'mfg_report_hpp' => 'Laporan HPP Manufaktur',

// Status
'mfg_status_open' => 'Terbuka',
'mfg_status_in_progress' => 'Sedang Diproses',
'mfg_status_completed' => 'Selesai',
'mfg_status_voided' => 'Dibatalkan',

// Process Types
'mfg_process_knitting' => 'Knitting (Rajut)',
'mfg_process_dyeing' => 'Dyeing (Celup)',
'mfg_process_printing' => 'Printing (Cetak)',
'mfg_process_finishing' => 'Finishing',
'mfg_process_cutting' => 'Cutting (Potong)',
'mfg_process_stitching' => 'Stitching (Jahit)',
'mfg_process_other' => 'Lainnya',

// Fabric States
'mfg_fabric_grey' => 'Kain Grey (Mentah)',
'mfg_fabric_finished' => 'Kain Finished (Jadi)',

// Finishing Stages
'mfg_stage_washing' => 'Washing (Cuci)',
'mfg_stage_ironing' => 'Ironing (Setrika)',
'mfg_stage_qc' => 'Quality Control',
'mfg_stage_packing' => 'Packing (Kemas)',
'mfg_stage_other' => 'Lainnya',

// Success Messages
'mfg_processing_order_created' => 'Processing Order :number berhasil dibuat.',
'mfg_material_receipt_created' => 'MRN :number berhasil dicatat & jurnal diposting.',
'mfg_work_order_completed' => 'SPK :number berhasil diselesaikan. HPP dihitung ulang.',
'mfg_void_success' => 'Dokumen :number berhasil dibatalkan. Jurnal pembalik diposting.',

// Error Messages
'mfg_processing_order_failed' => 'Gagal membuat Processing Order: :reason',
'mfg_material_receipt_failed' => 'Gagal mencatat MRN: :reason',
'mfg_fabric_stock_insufficient' => 'Stok kain :fabric_code tidak cukup. Tersedia: :available, Diminta: :requested',
'mfg_yarn_stock_insufficient' => 'Stok benang :yarn_code tidak cukup. Tersedia: :available, Diminta: :requested',
'mfg_work_order_not_found' => 'SPK ID :id tidak ditemukan.',
'mfg_work_order_already_completed' => 'SPK :number sudah selesai, tidak bisa diubah.',
'mfg_target_date_must_future' => 'Tanggal target harus hari ini atau di masa depan.',
'mfg_journal_not_balance' => 'Jurnal manufaktur tidak seimbang (Debet ≠ Kredit). Transaksi dibatalkan.',

// Form Labels
'mfg_fabric_code' => 'Kode Kain',
'mfg_fabric_type' => 'Jenis Kain',
'mfg_yarn_code' => 'Kode Benang',
'mfg_yarn_count' => 'Count Benang',
'mfg_gsm' => 'GSM (Gram per m²)',
'mfg_composition' => 'Komposisi',
'mfg_width' => 'Lebar',
'mfg_lot_number' => 'Nomor Lot',
'mfg_qty_issued' => 'Qty Dikeluarkan',
'mfg_qty_received' => 'Qty Diterima',
'mfg_wastage_kg' => 'Wastage (kg)',
'mfg_pieces_cut' => 'Pieces Dipotong',
'mfg_pieces_ok' => 'Pieces OK',
'mfg_pieces_rejected' => 'Pieces Reject',
```

**Versi English (lang/en/erp.php):**
```php
'mfg_module' => 'Manufacturing',
'mfg_status_open' => 'Open',
'mfg_status_in_progress' => 'In Progress',
'mfg_status_completed' => 'Completed',
'mfg_status_voided' => 'Voided',
'mfg_fabric_stock_insufficient' => 'Fabric stock :fabric_code is insufficient. Available: :available, Requested: :requested',
// ... (lanjutkan pola yang sama)
```

**Versi Simplified Chinese (lang/zh_CN/erp.php):**
```php
'mfg_module' => '生产模块',
'mfg_status_open' => '待处理',
'mfg_status_in_progress' => '进行中',
'mfg_status_completed' => '已完成',
'mfg_status_voided' => '已作废',
'mfg_fabric_stock_insufficient' => '面料 :fabric_code 库存不足。可用: :available，需求: :requested',
'mfg_process_knitting' => '针织',
'mfg_process_dyeing' => '染色',
'mfg_process_printing' => '印花',
'mfg_process_cutting' => '裁剪',
'mfg_process_stitching' => '缝制',
// ... (lanjutkan pola yang sama)
```

### 10.4 Error Handling Pattern (WAJIB di Semua Service Manufaktur)

Setiap Service di `App\Modules\Manufacturing\Services\` WAJIB mengikuti pola ini:

```php
<?php
namespace App\Modules\Manufacturing\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\JournalBalanceValidator;

class MaterialReceiptService
{
    public function createAndPost(array $data)
    {
        // 1. Validasi bisnis — lempar Exception dengan key i18n
        if (empty($data['items'])) {
            throw new Exception(__('erp.mfg_mrn_no_items'));
        }

        // 2. Cek stok cukup (jika berlaku)
        // ...

        // 3. Bangun jurnal
        $journalRows = $this->buildJournalRows($data);

        // 4. Validasi double-entry (RULES.md §2)
        if (!JournalBalanceValidator::isBalanced($journalRows)) {
            // Log internal TETAP bahasa Indonesia/Inggris
            Log::error('MRN journal not balance', ['data' => $data]);
            // Pesan ke user WAJIB i18n
            throw new Exception(__('erp.mfg_journal_not_balance'));
        }

        // 5. Atomic transaction
        DB::beginTransaction();
        try {
            // ... insert operations ...
            DB::commit();
            return $receipt;
        } catch (\Exception $e) {
            DB::rollBack();
            // Log internal
            Log::error('MRN creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            // Re-throw dengan pesan i18n (jika belum)
            if (str_starts_with($e->getMessage(), 'mfg.')) {
                throw $e; // sudah i18n
            }
            throw new Exception(__('erp.mfg_material_receipt_failed', [
                'reason' => __('erp.system_error_generic')
            ]));
        }
    }
}
```

### 10.5 Testing Checklist Modul Manufaktur

Sebelum merge PR yang mengubah modul manufaktur:

- [ ] Test create MRN di 3 bahasa → flash message muncul di bahasa aktif
- [ ] Test void SPK di 3 bahasa → alert konfirmasi & success message i18n
- [ ] Test export Excel HPP Report di 3 bahasa → header kolom terjemahan benar
- [ ] Test validation error (mis. stok kurang) di 3 bahasa
- [ ] Cek `SystemLog::record()` — tetap bahasa Indonesia (audit trail)
- [ ] Cek `\Log::error()` — tetap bahasa Indonesia/Inggris (developer log)
- [ ] Enum di DB (status, process_type) tidak berubah saat switch bahasa
- [ ] Print PDF MRN/SPK di 3 bahasa → label terjemahan benar

### 10.6 Migration Path (untuk kode yang sudah ada)

Kode manufaktur yang sudah terlanjur hardcoded **TIDAK perlu di-rewrite sekaligus**.
Lakukan bertahap:

1. **Sprint 1**: Tambahkan `ManufacturingLabel` helper + keys i18n di 3 file bahasa
2. **Sprint 2**: Update Blade files (prioritas: index & show pages)
3. **Sprint 3**: Update Controllers (flash messages)
4. **Sprint 4**: Update Services (exception messages)
5. **Sprint 5**: Update Exports (Excel headers)
6. **Sprint 6**: Jalankan `php artisan i18n:scan-hardcoded` untuk sisa hardcoded

**JANGAN** melakukan big-bang rewrite — risiko bug terlalu tinggi.

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
| `purchase_orders` + `po_items` (raw material) | `mfg_material_purchase_orders` + `mfg_material_purchase_order_details` | dipisah dari `purchase_orders` inti akuntansi (barang jadi/Jubelio) |
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

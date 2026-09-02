# ARCHITECTURE.md — Sistem ERP Akuntansi

> Status: **REVISI** (dokumen sebelumnya belum ada dalam bentuk terpisah — hanya tersirat di RULES.md lama). Disusun berdasarkan pembacaan penuh terhadap ±221 file kode sumber (Controllers, Services, Models, Jobs, Imports, Exports, Migrations, Views, Routes) per 25 Juli 2026.

## 1. Ringkasan Sistem

Aplikasi ini adalah **ERP Akuntansi berbasis Laravel 11 (PHP)**, dengan pola *server-rendered* Blade + jQuery/AJAX (bukan SPA/Inertia). Basis data diasumsikan **MySQL/MariaDB** (terlihat dari `DB::raw`, `whereMonth/whereYear`, penanganan `DATE_FORMAT` khusus MariaDB pada `BudgetingService`, dan batas *placeholder* 65.535 pada `FIX_SUMMARY.md`).

Sistem ini adalah **turunan/sinkronisasi dari data Jubelio** (platform omnichannel commerce pihak ketiga) yang diselaraskan ke buku besar akuntansi standar Indonesia (Debit/Kredit, COA berbasis prefix angka).

## 2. Gaya Arsitektur

**MVC + Service Layer**, secara eksplisit diwajibkan oleh RULES.md internal proyek:

```
Request → Route → Controller (validasi + orkestrasi ringan)
                 → Service (logika bisnis kompleks, multi-tabel, auto-journal)
                 → Model/Eloquent atau DB::table (akses data)
                 → View (Blade) / Export (Excel) / JSON (Ajax)
```

Bukan seluruh modul konsisten menerapkan pola ini — lihat §7 "Temuan Arsitektur".

### 2.1 Lapisan (Layers)

| Lapisan | Lokasi | Tanggung Jawab |
|---|---|---|
| Routing | `routes/web.php`, `routes/api.php`, `routes/console.php` | Definisi endpoint & middleware |
| Presentation | `resources/views/**/*.blade.php` (70 file) | UI form, tabel, laporan |
| Controller | `app/Http/Controllers/**` (33 file) | Validasi input, otorisasi dasar, pemanggilan Service |
| Service | `app/Services/**` (5 file inti) | Logika akuntansi/inventori kompleks, transaksi DB, posting jurnal otomatis |
| Model (Eloquent) | `app/Models/**` (24 file) | Representasi tabel, relasi, casting |
| Job (Queue) | `app/Jobs/**` (10 file) | Sinkronisasi background (dashboard cache, temp table processing) |
| Import/Export | `app/Imports`, `app/Exports` | ETL dari/ke Excel/CSV (Maatwebsite Excel) |
| Console Command | `app/Console/Commands/**` (9 file) | Operasi bulk (fast import, cleanup, audit) via terminal |
| Support | `app/Support/**` | Util murni (parsing angka, interval laporan) |
| Middleware | `app/Http/Middleware` | Lokalisasi bahasa (custom); auth bawaan Laravel |

### 2.2 Auth & Middleware

- Auth Laravel standar (`LoginController`, guard `auth`, `guest`).
- `SetLocaleMiddleware` kustom untuk multi-bahasa (ID/EN).
- Endpoint publik tanpa login: `/form-pengajuan` (portal pengajuan Payment Plan karyawan) dan webhook Jubelio (`/jubelio/webhook/sales`) — **CSRF dimatikan khusus untuk webhook ini** (perlu API-key/signature verification, lihat §7).
- Rate limiting (`throttle:`) dipakai konsisten pada endpoint import & form publik.

## 3. Modul Fungsional (Bounded Contexts)

| Modul | Controller/Service Utama | Model Inti |
|---|---|---|
| Chart of Accounts (COA) | `AccountController` | `Account` |
| Kode Bantu (Helper/Sub-ledger tag) | `HelperCodeController` | `HelperCode` |
| Jurnal Umum | `JournalController`, `JournalCsvImportService`, `JournalImport` | `JournalHeader`, `JournalDetail` |
| Buku Besar | `LedgerController` | (query gabungan `journal_headers`+`journal_details`) |
| Laporan Keuangan | `ProfitLossController`, `BalanceSheetController`, `CashFlowController`, `AdvancedReportController` | (agregasi read-only) |
| Purchase Order → Receiving → Bill | `PurchaseOrderController`, `PurchaseOrderService`, `PurchaseBillController` | `PurchaseOrder(Detail)`, `PurchaseBill(Detail)` |
| Sales Order → Shipment → Invoice | `SalesOrderController`, `SalesOrderService`, `SalesInvoiceController` | `SalesOrder(Detail)`, `SalesInvoice(Detail)` |
| Retur Penjualan/Pembelian | `SalesReturnController`, `PurchaseReturnController` | `SalesReturn(Detail)`, `PurchaseReturn(Detail)` |
| Inventori/Kartu Stok | `InventoryLedgerController`, `ProductController`, `WarehouseController` | `Product`, `InventoryLedger` |
| Aset Tetap & Depresiasi | `AssetController` | `Asset` |
| Payment Plan (Kas Kecil/Bank/Reimburse) | `PaymentPlanController`, `PaymentPlanService` | payment plan (tabel `transaksi_payment_plan`, belum ada Model Eloquent — akses via `DB::table`) |
| Budgeting & Forecast | `BudgetingController`, `BudgetingService` | agregasi `journal_details` |
| Master Data pendukung | `DivisiController`, `TaxController`, `CompanyProfileController`, `PaymentCategoryController`, `UserController` | `CompanyProfile`, `Tax`, `PaymentCategory`, `User` |
| Integrasi Jubelio | `Api\JubelioWebhookController`, `FastSyncJurnal`, `FastImportJurnal`, `FastImportPO`, `FastImportProduct`, `ImportHistoricalSales` | — |
| Audit/Log Sistem | `SystemLogController` | `SystemLog` |
| Penelusuran Dokumen | `DocumentTraceController` ("Smart Redirector") | lintas-modul |

## 4. Alur Data Utama (End-to-End)

### 4.1 Alur Penjualan (Sales)
```
SalesOrder (SO) --create--> [status: DRAFT/APPROVED]
   └─ SalesOrderService::createInvoiceAndShip()
        ├─ Validasi ulang total di server (anti tampering)
        ├─ Kunci baris SO & Product (lockForUpdate) → cegah race condition
        ├─ Buat SalesInvoice (INV-YYMMDD-####) + SalesInvoiceDetail
        ├─ Kurangi stok Product, cegah stok negatif (Exception jika kurang)
        ├─ Insert InventoryLedger (type OUT, moving average cost saat itu)
        ├─ Insert JournalHeader + JournalDetail (Piutang Dr / Penjualan Cr / HPP Dr / Persediaan Cr)
        ├─ Guard mutlak Debit == Kredit sebelum commit
        └─ SO.status = SHIPPED, invoice_id/invoice_no ditulis balik ke SO
   └─ voidShipment() = kebalikan penuh (hapus jurnal, kembalikan stok, hapus invoice)
```

### 4.2 Alur Pembelian (Purchase)
```
PurchaseOrder (PO) --create--> [status: DRAFT/APPROVED]
   └─ PurchaseOrderService::receivePartialOrder()
        ├─ Kunci PO & Product
        ├─ Deteksi apakah PO terkait Payment Plan "UANG MUKA" → pilih akun kredit
        │    (11305 = Uang Muka Pembelian jika ada DP, else 22000 = Hutang Usaha)
        ├─ Hitung Moving Average Cost baru per produk: (StokLama×HPPLama + QtyBaru×Harga) / StokBaru
        ├─ Insert InventoryLedger (type IN)
        ├─ Insert JournalHeader + JournalDetail (Persediaan Dr / Hutang atau UM Cr)
        ├─ PO.status = PARTIAL atau RECEIVED (tergantung qty_received vs qty)
   └─ voidReceipt() = kebalikan penuh (rollback stok+HPP, hapus jurnal & kartu stok)
```

### 4.3 Alur Jurnal Manual & Impor Massal
```
JournalController::store()  → validasi Debit==Kredit → insert JournalHeader+Detail (id = JRN-YYYYMMDD-000001, sequence per-hari, lockForUpdate)
JournalCsvImportService / JournalImport / FastImportJurnal / FastSyncJurnal
   → parsing CSV besar (Jubelio export) → deteksi format tanggal & angka (Indonesia vs US)
   → chunking (500 baris) untuk menghindari limit 65.535 placeholder MySQL
   → deteksi evidence_number prefix (GJ, INV, BIL, dst.) untuk pelaporan asal transaksi
```

### 4.4 Alur Retur
```
SalesReturn / PurchaseReturn → header + detail → proses (kembalikan stok) → jurnal balik (kredit/debit dibalik dari transaksi asal)
```

### 4.5 Alur Aset Tetap
```
Asset (dari input manual, import Excel, atau hasil re-klasifikasi JournalDetail belanja modal)
   → AssetController::generateDepreciation() → hitung penyusutan garis lurus
     (purchase_price - residual_value) / useful_life_months
   → posting jurnal beban penyusutan periodik (Beban Penyusutan Dr / Akumulasi Penyusutan Cr)
```

### 4.6 Alur Payment Plan (Kas Kecil/Bank/Reimburse)
```
Portal publik (/form-pengajuan) ATAU internal → PaymentPlanService::generateNoTransaksi()
   format: {MMYY}.{KodeDivisi}.{KodeJenis}.{tgl}.{urutan}  (bukan prefix alfabetik seperti INV/PO)
   → status: PENGAJUAN → APPROVED → PAID
   → postJournal() → set COA & rekening → posting jurnal (JRN-PP-<slug no_transaksi> sebagai journal_id deterministik)
   → Bisa memicu pembuatan PurchaseOrder otomatis (uang muka pembelian) — lihat §7.4
```

### 4.7 Sinkronisasi Jubelio
```
Jubelio (sumber eksternal) --webhook/CSV export-->
   JubelioWebhookController / FastImportJurnal / FastSyncJurnal / ImportHistoricalSales
   --> tabel temp_* (staging) --> Job (ProcessPendingTempJob, SyncDashboardToTempJob, dst.)
   --> tabel final (journal_headers, purchase_orders, sales_orders, dst.)
```
Pola *staging table + queued job* ini dipakai agar impor besar tidak memblokir request HTTP (timeout) dan agar proses bisa di-retry.

## 5. Peta Ketergantungan Antar Modul (Coupling Map)

```
                     ┌───────────────┐
                     │   Account     │◄──────────────┐ (account_code, FK longgar/implisit)
                     └──────┬────────┘                │
                             │                          │
     ┌───────────────────────┼─────────────────────────┼───────────────┐
     │                        │                          │                │
┌────▼─────┐   ┌─────────────▼──────┐   ┌───────────────▼───┐   ┌───────▼────────┐
│PurchaseOrder│→│ PurchaseBill/Return │→ │  JournalHeader/     │←  │  Asset (via     │
│  (PO)      │  │  (BIL / PR)         │  │  JournalDetail      │   │ journal_detail_id)
└────┬───────┘   └─────────────────────┘   │  (JRN / GJ)         │   └────────────────┘
     │ (kartu stok)                        └───────────▲─────────┘
     ▼                                                   │
┌──────────┐   ┌───────────────┐   ┌──────────────┐    │
│  Product  │◄─►│InventoryLedger│   │ SalesInvoice  │────┘
│(stok, HPP)│   │  (kartu stok) │◄──│  (INV)        │
└──────────┘   └───────────────┘   └──────┬────────┘
                                            │
                                    ┌───────▼────────┐
                                    │  SalesOrder     │
                                    │  (SO / POS)      │
                                    └──────┬──────────┘
                                            │
                                    ┌───────▼────────┐
                                    │  SalesReturn    │
                                    │  (SR)           │
                                    └─────────────────┘

PaymentPlan (kode nomor tersendiri, bukan prefix huruf) ──posting──► JournalHeader (journal_id = JRN-PP-...)
                                     └── bisa memicu ──► PurchaseOrder (uang muka pembelian)
```

**Kesimpulan penting**: semua modul transaksional (PO, SO, Invoice, Bill, Return, Asset, Payment Plan) pada akhirnya **selalu bermuara ke satu titik tunggal**: `journal_headers` + `journal_details`. Ini adalah *single source of truth* pembukuan — namun relasi ke sana **tidak memakai foreign key formal** (tidak ada `journal_id` FK constraint dari `sales_invoices`/`purchase_orders` ke `journal_headers`); keterkaitan hanya melalui **`evidence_number` (string, non-FK)**. Lihat §7.1 untuk implikasi risiko.

## 6. Konkurensi & Integritas Data

- **Row locking** (`lockForUpdate()`) diterapkan konsisten di titik-titik kritis: `SalesOrder`, `PurchaseOrder`, `Product` (stok/HPP), `JournalHeader::generateNextId()`.
- **DB Transaction wrapping** (`DB::beginTransaction/commit/rollBack`) diterapkan di semua Service inti (`SalesOrderService`, `PurchaseOrderService`, `PaymentPlanService`).
- **Guard balance mutlak**: setiap kumpulan `journalLines` divalidasi `round(sumDebet,2) == round(sumKredit,2)` sebelum `insert()`; jika tidak, `Exception` dilempar dan transaksi di-rollback — pola ini **diulang mandiri di 3 tempat berbeda** (`JournalController`, `JournalImport`, `SalesOrderService`) — lihat risiko duplikasi logika di §7.2.
- **Batch insert/chunking** dipakai luas untuk operasi big-data (CSV import Jubelio, sinkronisasi bulk) guna menghindari limit *placeholder* MySQL (65.535) dan N+1 query.

## 7. Temuan Arsitektur (Isu & Risiko)

1. **Journal linkage tanpa Foreign Key formal.** Semua modul transaksi (`SalesInvoice`, `PurchaseBill`, dst.) terhubung ke `journal_headers` hanya lewat string `evidence_number`, bukan FK berelasi. Risiko: baris jurnal yatim (orphan) jika ada mismatch penulisan nomor, dan query pelacakan (`DocumentTraceController`) bergantung pada konsistensi string manual, bukan constraint DB.
2. **Duplikasi logika validasi balance.** Pengecekan "Debit harus sama dengan Kredit" ditulis ulang secara manual di `JournalController::store/update`, `JournalImport`, dan `SalesOrderService` — bukan di-ekstrak ke satu helper/trait bersama (`JournalBalanceValidator`). Risiko: jika aturan berubah (mis. toleransi pembulatan), harus diubah di banyak tempat, rawan lupa satu titik.
3. **Hardcoded account_code di banyak Service/Controller.** Ditemukan >30 kode akun (`11100`, `44000`, `55000`, `21104`, dst.) tertanam langsung sebagai string literal di `SalesOrderService`, `PurchaseOrderService`, dan controller laporan — bukan lewat konfigurasi/mapping terpusat (`config/coa_mapping.php` misalnya). Ini adalah **TEMUAN 5** yang sudah pernah diidentifikasi dalam laporan audit internal proyek namun **belum diperbaiki** per snapshot ini.
4. **Skema migrasi tidak sinkron dengan kondisi database aktual.** Tabel `journal_details` di migration awal (`2026_05_05_043810`) hanya punya kolom `journal_detail_id`, `debit`, `credit` — namun seluruh kode aplikasi (`JournalDetail` model, semua Service) memakai kolom `id`, `position`, `amount` yang **tidak pernah dibuat oleh migration manapun** dalam snapshot ini. Demikian pula `journal_headers.evidence_number` diberi index oleh migration `2026_06_20_031249` padahal kolom tersebut **tidak pernah di-`Schema::create`/`Schema::table`-kan**. Ini mengindikasikan riwayat migrasi di repo **tidak lagi merepresentasikan skema produksi sebenarnya** (kemungkinan ada migration yang hilang dari export, atau skema diubah manual di server). **Ini adalah temuan kritis** — lihat SCHEMA.md yang mendokumentasikan skema *efektif* (dari pemakaian kode), bukan hanya migration file.
5. **`$guarded = []` pada beberapa model transaksi** (`PurchaseBill`, `PurchaseBillDetail`, `PurchaseReturn` — dan kemungkinan `SalesReturn` perlu diverifikasi ulang) membuka mass-assignment penuh, termasuk kemungkinan menimpa primary key/foreign key dari request tanpa sengaja. Sudah teridentifikasi sebagai `BUG-05` di audit internal, **status: belum diperbaiki** pada snapshot ini.
6. **Model Eloquent tidak lengkap untuk `transaksi_payment_plan`.** `PaymentPlanService` mengakses tabel ini murni lewat `DB::table()`, tanpa Model Eloquent — tidak konsisten dengan pola modul lain (yang punya Model dedicated). Ini mempersulit reuse relasi/casting/observer.
7. **Webhook publik tanpa middleware CSRF** (`/jubelio/webhook/sales`) — perlu dipastikan ada mekanisme verifikasi lain (signature/secret token) di dalam `JubelioWebhookController`, karena pengecualian CSRF pada endpoint publik adalah titik rawan jika tidak diverifikasi ketat.
8. **Inkonsistensi klasifikasi akun antar-modul laporan.** `ProfitLossController::determineAccountGroup()` sudah diperbaiki menjadi dinamis (berbasis `normal_balance` dari master COA) untuk prefix 7/8/9, tetapi `BudgetingService::ACCOUNT_CATEGORIES` **masih statis** dan memperlakukan seluruh prefix 6–9 sebagai "Beban Operasional" tanpa mempertimbangkan saldo normal. Modul Budgeting berpotensi menghasilkan angka proyeksi yang berbeda dari Laporan Laba Rugi resmi untuk akun prefix 7–9.

Detail lengkap tiap temuan beserta rekomendasi teknis: lihat **RULES.md §7 "Catatan Konsistensi & Isu Terbuka"**.

## 8. Deployment/Operasional (yang teramati dari kode)

- Command `php artisan jurnal:check-balance {start} {end}` untuk audit rutin balance jurnal.
- Command `asset:cleanup-orphan`, `asset:process-existing`, `asset:sync` untuk pemeliharaan data aset.
- Command `import:po-fast`, `import:product-fast`, `jurnal:fast-import`, `jurnal:fast-sync`, `sales:import-historical` untuk migrasi data awal/besar dari Jubelio, dengan `ini_set('memory_limit', -1)` dan `DB::disableQueryLog()` wajib.
- Queue Job untuk sinkronisasi dashboard (`SyncDashboardToTempJob` dan varian per-modul: Bill/Inv/PO/SO) — pola *cache warm-up* ke tabel temp agar Dashboard tidak query berat tiap load.

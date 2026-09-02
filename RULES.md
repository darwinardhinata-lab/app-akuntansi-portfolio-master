# RULES.md - Panduan Standar Pengembangan Sistem ERP Akuntansi

> Status: **REVISI**. Dokumen `RULES.md` versi asli sudah ditemukan tertanam di riwayat percakapan proyek (ditulis ±2026‑07‑20). Isinya secara umum masih **valid dan dipatuhi oleh sebagian besar kode**, namun beberapa poin perlu koreksi/tambahan setelah verifikasi ulang terhadap kode sumber terbaru (25 Juli 2026). Perubahan terhadap versi asli ditandai **[BARU]** atau **[KOREKSI]**.

Dokumen ini berisi aturan mutlak (SOP) arsitektur dan *codebase* aplikasi ERP Akuntansi. Segala bentuk penambahan fitur, *refactoring*, atau perbaikan *bug* **WAJIB** mematuhi aturan di bawah ini untuk mencegah kerusakan integritas data akuntansi dan performa sistem.

## 1. Arsitektur & Pola Desain (Design Pattern)

* **MVC + Service Layer:** Gunakan Controller HANYA untuk menerima *request*, validasi input dasar, dan mengembalikan *response* (View/JSON). Logika bisnis yang kompleks, multi-tabel, atau memicu penjurnalan otomatis **WAJIB** diletakkan di dalam `App\Services\` (contoh: `PurchaseOrderService`, `SalesOrderService`, `PaymentPlanService`).
* **Modularitas:** Fitur spesifik yang berdiri sendiri (seperti Manufaktur/Pabrikasi — sudah dirujuk `DocumentTraceController` dengan prefix `MFG`/`SPK` meski modulnya belum diimplementasikan) harus menggunakan pendekatan modular (terisolasi di `App\Modules\`) agar tidak mengganggu *core* akuntansi utama.
* **[KOREKSI]** Aturan "logika kompleks wajib di Service" **belum konsisten diterapkan**. `AssetController`, `PaymentPlanController` (sebagian), dan beberapa controller laporan (`ProfitLossController`, `BalanceSheetController`, `CashFlowController`) masih menaruh kalkulasi bisnis substansial langsung di Controller. Ini boleh dipertahankan untuk *read-only report* (murni query/agregasi), tetapi operasi yang **menulis data & jurnal** (seperti generate depresiasi Aset) sebaiknya dipindah ke Service khusus (`AssetService`) mengikuti pola `SalesOrderService`/`PurchaseOrderService`.

## 2. Aturan Mutlak Akuntansi (Core Accounting Logic)

Sistem ini menggunakan standar akuntansi riil. Jangan pernah melakukan modifikasi (bypass) pada logika berikut:

* **Double-Entry Validation:** Setiap transaksi jurnal (`JournalHeader` dan `JournalDetail`) **WAJIB** memiliki total `DEBET` yang sama persis dengan total `KREDIT` hingga 2 angka desimal (`round($val, 2)`, dibandingkan dengan `bccomp()` untuk presisi desimal). Jika terjadi selisih 0.01 sekalipun, sistem **WAJIB `DB::rollBack()`**.
* **Hierarki Awalan COA (Chart of Accounts)** — **[KOREKSI]** versi asli menyebut hanya prefix 1–8; berdasarkan `ProfitLossController::determineAccountGroup()` yang telah diperbaiki, aturan yang benar mencakup 1–9 dengan penanganan dinamis untuk 7–9:
  * `1` = Aset / Harta (Saldo Normal: DEBET)
  * `2` = Kewajiban / Hutang (Saldo Normal: KREDIT)
  * `3` = Modal / Ekuitas (Saldo Normal: KREDIT)
  * `4` = Pendapatan / Penjualan (Saldo Normal: KREDIT)
  * `5` = Harga Pokok Penjualan (HPP / COGS) (Saldo Normal: DEBET)
  * `6` = Biaya / Beban Operasional (Saldo Normal: DEBET)
  * `7`, `8`, `9` = Pendapatan Lain-lain **atau** Beban Lain-lain — **arah (Debet/Kredit) TIDAK ditentukan oleh prefix semata**, melainkan **wajib dibaca dari `accounts.normal_balance`** pada baris akun tersebut. Jika `normal_balance = KREDIT` → masuk kelompok "Pendapatan Lainnya"; jika `DEBET` → masuk "Beban Lain-lain". **[BARU]** Modul manapun yang mengelompokkan akun prefix 7–9 (laporan baru, dashboard, budgeting) **WAJIB** memakai logika dinamis ini — **DILARANG** hardcode "prefix 8/9 = beban" seperti yang masih ditemukan di `BudgetingService::ACCOUNT_CATEGORIES` saat ini (lihat §7.5, perlu perbaikan).
* **Larangan Hard-Delete:** Jangan pernah menghapus akun (COA) atau Kode Bantu yang sudah memiliki relasi riwayat transaksi (`journalDetails()->count() > 0`).

## 3. Aturan Operasi Database (Eloquent & Raw SQL)

* **Database Transaction:** Setiap operasi yang mengubah lebih dari 1 tabel (misal: Create PO Header + PO Detail, atau posting jurnal) **WAJIB** dibungkus dalam:
```php
DB::beginTransaction();
try {
    // Logic
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Handle error
}
```

* **Pencegahan Race Condition (Stok & Jurnal):** Saat melakukan pemotongan atau penambahan stok barang, atau saat mengubah status header transaksi (SO/PO), **WAJIB** menggunakan penguncian baris database: `Product::lockForUpdate()->find($id)` / `SalesOrder::lockForUpdate()->findOrFail($id)` untuk mencegah data bentrok saat diakses bersamaan. Pola ini sudah diterapkan konsisten di `SalesOrderService` dan `PurchaseOrderService` (ditandai komentar `B13 FIX` di kode — pertahankan pola ini pada Service baru).
* **Primary Key Non-Standar:** Tabel `Account`, `HelperCode`, dan `JournalHeader` menggunakan **String Primary Key** (`account_code`, `helper_code`, `journal_id`). Saat memanggil `find()`, relasi Eager Loading, atau `insert()`, pastikan formatnya string dan bukan auto-increment integer. Pada modelnya wajib terdefinisi:
```php
public $incrementing = false;
protected $keyType = 'string';
```
* **[BARU] Mass Assignment:** **DILARANG** menggunakan `protected $guarded = [];` pada model yang mewakili tabel transaksi keuangan (di mana baris menjadi sumber jurnal). Saat ini `PurchaseBill`, `PurchaseBillDetail`, dan `PurchaseReturn` masih memakai `$guarded = []` — ini **harus diperbaiki** menjadi `$fillable` eksplisit (mengikuti pola `HelperCode` yang sudah diperbaiki dengan komentar "B5 FIX"). Lihat §7.4.
* **[BARU] Referensi Antar Modul:** Setiap kali sebuah dokumen (Invoice/Bill/Return/Payment) memicu jurnal, **WAJIB** mengisi `journal_headers.evidence_number` dengan nomor dokumen sumber yang identik dengan nomor yang tersimpan di tabel dokumen itu sendiri (`invoice_number`, `bill_number`, `po_number`, dst.), agar `DocumentTraceController` dan laporan sub-ledger (`AdvancedReportController`) dapat menelusurinya. **DILARANG** mengubah format prefix key (lihat §"KEY & Prefix Dokumen" di bawah) tanpa memperbarui `DocumentTraceController::trace()` secara bersamaan.

## 4. Standar Manajemen Memori & Big Data (Imports / Export)

Sistem ini sering memproses ratusan ribu baris data CSV (Jubelio/Sistem Eksternal). Untuk Command Terminal (`app/Console/Commands/`) atau Controller yang menangani Import/Report Matriks:

* **Matikan Limit di Awal Method:**
```php
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0); // Atau 1200 / 3600
DB::disableQueryLog(); // MUTLAK! Jika tidak, RAM Laravel akan bocor menampung log.
```
* **Gunakan Chunking/Batch Insert (maksimal 500 baris per batch):** Dilarang melakukan iterasi `Model::create()` di dalam *loop* ratusan data. Tampung dalam *array*, lalu gunakan `insert()` setiap batas tertentu. Batas 500 dipilih secara spesifik untuk menghindari limit **65.535 placeholder MySQL** pada prepared statement (lihat `FIX_SUMMARY.md` internal — bug SQLSTATE 1390 pernah terjadi di `JournalImport`, `JournalCsvImportService`, `FastImportJurnal`, `FastSyncJurnal` dan sudah diperbaiki).
```php
if (count($detailsToInsert) >= 500) {
    DB::table('journal_details')->insert($detailsToInsert);
    $detailsToInsert = [];
}
```
* **Gunakan `DB::table` untuk Agregasi:** Untuk laporan seperti Buku Besar, Laba Rugi, dan Arus Kas, **dilarang** menggunakan Eloquent Collection (`get()->where()`) untuk manipulasi ribuan data. Gunakan *Query Builder* (`DB::table(...)->selectRaw(...)`) lalu kelompokkan hasilnya.
* **[BARU] Kompatibilitas MariaDB:** Hindari `DATE_FORMAT` di dalam klausa `GROUP BY` (bermasalah di MariaDB — lihat komentar di `BudgetingService::getMonthlyData()`); ambil data harian dahulu lalu agregasi bulanan di level PHP.

## 5. Sistem Inventori & HPP (Moving Average)

* **Kartu Stok (`InventoryLedger`):** Setiap barang yang masuk (IN) atau keluar (OUT) dari gudang wajib dicatat di tabel `inventory_ledgers` yang berisi HPP (`unit_cost`), dan Nilai Persediaan Berjalan (`running_value`).
* **Kalkulasi HPP Aktual:** HPP (`average_cost`) pada tabel `Product` harus dihitung ulang (update) secara otomatis **setiap kali ada barang masuk (Penerimaan PO / Hasil Produksi SPK)** menggunakan metode Rata-Rata Tertimbang (*Moving Average*): `newMac = ((oldStock × oldMac) + (qtyMasuk × hargaBeli)) / newStock`.
* **[BARU] Validasi Stok Keluar:** Setiap pengurangan stok (Shipment SO, Retur Pembelian) **WAJIB** divalidasi `qtyKeluar <= stock_quantity` sebelum diproses — lempar `Exception` jika stok tidak cukup (pola `H1 FIX` di `SalesOrderService`). **DILARANG** membiarkan `stock_quantity` menjadi negatif.
* **[BARU] Validasi Qty Terima:** Penerimaan PO **WAJIB** divalidasi `qtyTerima <= (qtyPO - qtyReceivedSebelumnya)` (pola `H2 FIX` di `PurchaseOrderService`).

## 6. Do's and Don'ts (Boleh vs Dilarang)

✅ **BOLEH DILAKUKAN:**
* Menambahkan kolom baru pada *migration* menggunakan `Schema::table()`.
* Membuat module/folder baru di luar *core* (misal: Modul HRD, Modul CRM, Modul Manufaktur/`MFG`) selama tidak langsung memodifikasi tabel jurnal.
* Menambahkan index MySQL di *migration* untuk mempercepat kueri *Big Data*.
* Memisahkan logika dari Controller yang sudah terlalu panjang ke `App\Services\`.
* **[BARU]** Menambahkan prefix key dokumen baru (mis. `MFG-`, `SPK-`) — **dengan syarat** langsung didaftarkan ke `DocumentTraceController::trace()` pada saat yang sama (lihat §"KEY & Prefix Dokumen").

❌ **DILARANG KERAS DILAKUKAN:**
* Mengubah tipe data primary key `account_code`, `helper_code`, atau `journal_id` menjadi `id` (Auto-increment BigInt). Ini akan merusak jutaan baris riwayat data historis.
* Memotong tahapan pembuatan jurnal di Modul *Sales Invoice* (Penjualan), *Penerimaan PO* (Pembelian), atau *Penyelesaian SPK* (Manufaktur). Ketiga *event* ini adalah **detak jantung** akuntansi sistem.
* Melakukan format *rupiah* (`number_format`) sebelum data disimpan ke Database. Semua *parser* harus membersihkan koma dan titik (`NumberParser::parseDecimal()`) menjadi desimal float murni sebelum di `insert()` / `update()`.
* Mematikan *Foreign Key Check* (`SET FOREIGN_KEY_CHECKS=0;`) di level Controller web biasa. Perintah ini hanya eksklusif diizinkan pada *Console Commands* Fast Import (Terminal) untuk mempercepat sinkronisasi awal.
* **[BARU]** Menghapus atau mengubah prefix key dokumen yang sudah beredar di data historis (`INV`, `PO`, `SO`, `BIL`, `SR`, `PR`, `GJ`, `JRN`) tanpa migrasi data — akan merusak seluruh fitur pelacakan dokumen (`DocumentTraceController`) dan laporan sub-ledger.
* **[BARU]** Menambahkan hardcoded `account_code` baru langsung di Controller/Service tanpa mendokumentasikannya — pertimbangkan memindahkan seluruh mapping akun ke satu file konfigurasi terpusat (`config/coa_mapping.php`) sebagai *technical debt* prioritas (lihat §7.3).

## 7. Catatan Konsistensi & Isu Terbuka (Hasil Audit Ulang 25 Juli 2026)

Bagian ini menjawab pertanyaan: *"apakah logic, perhitungan, struktur sudah dalam satu flow yang tidak terpisahkan?"* — Jawaban: **sebagian besar YA** untuk jalur transaksi inti (PO→Bill→Jurnal, SO→Invoice→Jurnal, semuanya bermuara konsisten ke `journal_headers`/`journal_details` dengan validasi balance yang seragam), namun ada beberapa titik **tidak sepenuhnya menyatu**:

1. **Journal linkage tanpa FK formal** — keterhubungan modul transaksi ke jurnal hanya via string `evidence_number`, bukan constraint database. (Detail: ARCHITECTURE.md §7.1)
2. **Validasi balance ditulis ulang di 3 tempat** (`JournalController`, `JournalImport`, `SalesOrderService`) alih-alih 1 helper terpusat. (Detail: ARCHITECTURE.md §7.2)
3. **Hardcoded account_code tersebar** di >30 titik kode (SalesOrderService, PurchaseOrderService, Controller laporan) — bukan di satu file konfigurasi. (Detail: ARCHITECTURE.md §7.3)
4. **`$guarded = []` belum diperbaiki** pada `PurchaseBill`, `PurchaseBillDetail`, `PurchaseReturn` — model transaksi keuangan seharusnya memakai `$fillable` eksplisit seperti `HelperCode` yang sudah diperbaiki. (BUG-05 dari audit internal, status: **masih terbuka**)
5. **`BudgetingService` belum mengikuti perbaikan klasifikasi dinamis akun 7–9** yang sudah diterapkan di `ProfitLossController` — proyeksi Budgeting berisiko berbeda dari Laporan Laba Rugi resmi untuk akun-akun tersebut.
6. **Skema migrasi tidak lengkap** — kolom `journal_headers.evidence_number` dan sebagian besar kolom `journal_details` (`id`, `position`, `amount`) yang dipakai luas oleh kode **tidak ditemukan pada file migration create manapun** dalam snapshot ini. Lihat SCHEMA.md §9 untuk rekomendasi.
7. **Payment Plan memakai format nomor berbeda** dari seluruh modul lain (bukan prefix alfabetik `XXX-`, melainkan `MMYY.Divisi.Jenis.Tgl.Urutan`) — ini disengaja (sistem approval internal, bukan dokumen niaga eksternal), namun perlu didokumentasikan eksplisit agar developer baru tidak salah asumsi saat membaca `DocumentTraceController` (yang menangani `PP` sebagai kasus khusus, redirect ke jurnal, bukan ke tabel `transaksi_payment_plan` langsung).

---

## 8. KEY & Prefix Dokumen (Document Identification Keys)

Ini adalah **kamus resmi** seluruh awalan (prefix) nomor dokumen yang dikenali sistem, bersumber dari `App\Http\Controllers\DocumentTraceController::trace()` — satu-satunya tempat di kode yang mendefinisikan pemetaan prefix→modul secara eksplisit dan lengkap.

### 8.1 Daftar Lengkap KEY

| KEY | Kepanjangan | Tabel Tujuan | Kolom Nomor | Format Aktual (dari kode generator) | Modul |
|---|---|---|---|---|---|
| `PO` | Purchase Order | `purchase_orders` | `po_number` | (diinput manual, unik) | Pembelian |
| `SO` | Sales Order | `sales_orders` | `so_number` | `SO-YYMMDD-####` (4 digit random) | Penjualan |
| `POS` | Point of Sales | `sales_orders` (sama tabel dengan SO) | `so_number` | mengikuti format SO | Penjualan (kanal ritel) |
| `INV` | Invoice / Faktur Penjualan | `sales_invoices` | `invoice_number` | `INV-YYMMDD-####` (sequence harian) | Penjualan |
| `SR` | Sales Return (Retur Penjualan) | `sales_returns` | `return_number` | `SR-YYYYMMDD-####` (sequence) | Penjualan |
| `PR` | Purchase Return (Retur Pembelian) | `purchase_returns` | `return_number` | `PR-YYYYMMDD-XXXX` (uniqid 4 char) | Pembelian |
| `BIL` | Bill (Tagihan Pembelian) | `purchase_bills` | `bill_number` | `BIL-YYYYMMDD-XXXX` (uniqid 4 char) — juga dipakai sbg fallback `evidence_number` penerimaan PO: `BIL-{po_number}-XXXX` | Pembelian |
| `MFG` | Manufaktur (Surat Perintah Kerja) | *(belum ada tabel — placeholder)* | — | — | Manufaktur (belum diimplementasikan penuh) |
| `SPK` | Surat Perintah Kerja | *(alias dari MFG)* | — | — | Manufaktur |
| `SA` | Saldo Awal (Setup) | *(tidak ada dokumen sumber fisik)* | — | — | Setup Awal |
| `OB` | Opening Balance | *(alias dari SA)* | — | — | Setup Awal |
| `GJ` | General Journal (dari Jubelio) | `journal_headers` | `evidence_number` | Berasal dari sinkronisasi Jubelio (jurnal manual yang diinput langsung, tanpa dokumen operasional sumber) | Jurnal / Sinkronisasi |
| `JRN` | Jurnal Manual (internal) | `journal_headers` | `journal_id` (PK) | `JRN-YYYYMMDD-000001` (sequence harian, 6 digit, dengan row-lock anti race condition) | Jurnal Umum |
| `PP` | Payment Plan | `journal_headers` (hasil posting) | `journal_id` = `JRN-PP-<slug no_transaksi>` | Nomor transaksi asli: `{MMYY}.{KodeDivisi}.{KodeJenis}.{tgl}.{urutan}` (BUKAN prefix `PP-`, melainu dipetakan ke `JRN-PP-` hanya saat posting ke jurnal) | Payment Plan (Kas Kecil/Bank) |

### 8.2 Pengelompokan KEY Berdasarkan Domain Bisnis

**A. Domain Pembelian (Purchase / Procurement)**
- `PO` — Purchase Order (pemesanan ke supplier)
- `BIL` — Purchase Bill (tagihan/penerimaan barang dari PO)
- `PR` — Purchase Return (retur barang ke supplier)

**B. Domain Penjualan (Sales)**
- `SO` — Sales Order (pesanan dari pelanggan)
- `POS` — Point of Sales (varian SO untuk kanal ritel/toko fisik)
- `INV` — Sales Invoice (faktur, dibuat saat SO dikirim/shipped)
- `SR` — Sales Return (retur barang dari pelanggan)

**C. Domain Jurnal & Pembukuan (General Ledger)**
- `JRN` — Jurnal manual buatan user internal aplikasi
- `GJ` — Jurnal hasil sinkronisasi dari Jubelio (general journal eksternal)
- `SA` / `OB` — Saldo Awal / Opening Balance (entry non-transaksional, murni setup)

**D. Domain Manufaktur (belum diimplementasikan penuh — hanya placeholder routing)**
- `MFG` / `SPK` — Surat Perintah Kerja (produksi)

**E. Domain Keuangan Internal / Kas (Non-Dokumen Niaga)**
- `PP` — Payment Plan (pengajuan kas kecil/bank/reimburse karyawan → di-posting sebagai jurnal `JRN-PP-...`)

### 8.3 Pengelompokan KEY Berdasarkan Arah Alur Data

| Arah | KEY yang Terlibat |
|---|---|
| **Uang keluar / Beban / Hutang** | `PO → BIL` (barang & hutang masuk), `PR` (barang keluar, hutang berkurang), `PP` (kas kecil/reimburse keluar) |
| **Uang masuk / Pendapatan / Piutang** | `SO/POS → INV` (piutang & pendapatan terbentuk), `SR` (barang masuk kembali, piutang berkurang) |
| **Non-operasional / Setup / Manual** | `SA/OB` (saldo awal), `JRN` (jurnal manual), `GJ` (jurnal impor eksternal) |
| **Belum aktif (reserved)** | `MFG/SPK` |

### 8.4 Aturan Penambahan KEY Baru

Jika modul baru ditambahkan (misal Modul Manufaktur diaktifkan penuh dengan prefix `MFG-XXXXX`):
1. Tentukan format nomor unik + prefix baru (huruf kapital, dipisah `-` dari sequence/tanggal).
2. Tambahkan `case` baru di `DocumentTraceController::trace()`.
3. Pastikan kolom `evidence_number` pada `journal_headers` diisi persis dengan nomor dokumen tersebut saat posting jurnal otomatis dari modul baru.
4. Update tabel §8.1 di dokumen ini.
5. Pertimbangkan menambahkan constraint/enum di level aplikasi (bukan wajib di level DB, karena `evidence_number` bersifat lintas-modul) untuk mencegah prefix baru bentrok dengan yang sudah ada.

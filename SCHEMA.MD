# SCHEMA.MD — Skema Basis Data ERP Akuntansi

> Status: **REVISI/BARU**. Disusun dari 45 file migration + seluruh pemakaian `Model::$fillable`/`DB::table()` di kode aplikasi.
> ⚠️ **Catatan Penting**: Beberapa kolom yang **dipakai aktif oleh kode aplikasi TIDAK ditemukan pada file migration manapun** dalam snapshot ini (kemungkinan migration hilang dari export, atau skema di-patch manual di server produksi). Kolom demikian ditandai **`[INFERRED]`** — didokumentasikan berdasarkan penggunaan di kode (Model `$fillable`, query), bukan berdasarkan file migration yang ditemukan. Ini WAJIB diverifikasi langsung ke `information_schema` server produksi.

## 1. Tabel Inti Akuntansi (Core Ledger)

### 1.1 `accounts` (Chart of Accounts / COA)
Migration: `2026_05_05_043745_create_accounts_table.php` + `2026_06_29_042345_add_coa_id_to_accounts_table.php`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `account_code` | string(20), **PK**, non-increment | Kode akun, mis. `110101`. **Bukan auto-increment** — dilarang diubah jadi integer PK (RULES.md). |
| `account_name` | string(100) | Nama akun |
| `coa_type` | string(50) | Tipe COA (ASSET, LIABILITY, dst.) |
| `normal_balance` | enum(DEBET, KREDIT) | Saldo normal akun — dipakai dinamis oleh Laporan Laba Rugi/Neraca |
| `report_pos` | enum(NERACA, LABA RUGI) | Posisi pelaporan |
| `coa_id` | (ditambahkan) | Kolom tambahan referensi ID [External Platform] |
| timestamps | | |

### 1.2 `journal_headers`
Migration: `2026_05_05_043759_create_journal_headers_table.php`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `journal_id` | string(50), **PK**, non-increment | Format: `JRN-YYYYMMDD-000001` (manual) atau ID asli [External Platform], atau `JRN-PP-<slug>` (dari Payment Plan) |
| `journal_no` | string(150) nullable | Nomor jurnal dari [External Platform] |
| `transaction_date` | datetime | |
| `source_doc_no` | string(150) nullable | Nomor dokumen sumber |
| `payment_id`, `invoice_id`, `bill_id`, `sales_ret_id`, `purch_ret_id`, `item_adj_id` | integer nullable | Tracking ID asal dari [External Platform] |
| `is_opening_balance` | boolean default false | Penanda saldo awal |
| `debit`, `credit` | float(16,4) nullable | Total header (agregat) |
| `notes` | text nullable | |
| `journal_type`, `transaction_type` | string(100) nullable | |
| `tags` | string(255) nullable | Nama toko/channel (mis. "Shop \| Tokopedia") |
| `evidence_number` | string **[INFERRED — tidak ada di migration create, hanya di-index oleh migration `2026_06_20_031249`]** | **Field paling penting untuk penelusuran dokumen** — dipakai `DocumentTraceController`, semua Service (`SalesOrderService`, `PurchaseOrderService`, dst.) sebagai jembatan ke dokumen sumber (INV-xxx, BIL-xxx, dll). **Wajib diverifikasi keberadaannya di DB produksi.** |
| `jj_id` | **[INFERRED]** | Dummy timestamp ID, di-inject saat `creating()` agar insert tidak ditolak MySQL |
| timestamps | | |

### 1.3 `journal_details`
Migration: `2026_05_05_043810_create_journal_details_table.php` + `2026_05_08_031333_add_helper_code...` + `2026_06_27_105700_alter_amount_column...`

| Kolom | Tipe (migration asli) | Tipe/pemakaian aktual di kode | Keterangan |
|---|---|---|---|
| `journal_detail_id` | integer, PK | — | PK asli (skema [External Platform] import) |
| `id` | **[INFERRED]** | integer, PK auto-increment (dipakai `JournalDetail` Model: `$primaryKey='id'`, `$incrementing=true`) | **Tidak ada di migration manapun.** Kemungkinan besar `journal_detail_id` di-rename/di-drop dan `id` bawaan Laravel ditambahkan via migration yang hilang dari snapshot ini. |
| `journal_id` | string(50) nullable, indexed | FK logis ke `journal_headers.journal_id` (tanpa constraint FK formal) | |
| `account_code` | string(50) nullable, indexed | FK logis ke `accounts.account_code` | |
| `account_name` | string(250) nullable | — | |
| `helper_code` | string(20) nullable, **FK formal** → `helper_codes.helper_code` (added `2026_05_08`) | Kode sub-ledger (customer/vendor/karyawan) | `onDelete: set null` |
| `description` | text nullable | | |
| `debit`, `credit` | float(16,4) nullable | — model tidak memakai kolom ini | Skema lama (dual-column) |
| `position` | **[INFERRED]** | enum-like string `'DEBET'`/`'KREDIT'` | Skema baru (single-column + arah) — **dipakai di 100% kode aplikasi saat ini**, bukan `debit`/`credit` terpisah |
| `amount` | decimal(20,2) **[diubah oleh migration `2026_06_27`, awalnya float(16,4) — tapi kolom dasarnya sendiri tidak tercipta di migration create]** | Nilai transaksi per baris | |
| `account_id` | **[INFERRED]** | dipakai oleh `ProcessPendingTempJob` | |
| `journal_no` | **[INFERRED]** | dipakai oleh `ProcessPendingTempJob` | |
| timestamps | | | |

> **Kesimpulan skema efektif `journal_details`**: kolom yang benar-benar dipakai aplikasi = `id, journal_id, account_code, helper_code, description, position, amount` (+ `account_id`, `journal_no` khusus proses staging). Kolom `journal_detail_id`, `debit`, `credit`, `account_name` dari migration lama tampaknya **legacy/tidak lagi dipakai** oleh flow utama.

### 1.4 `helper_codes` (Kode Bantu / Sub-ledger)
Migration: `2026_05_05_045914_create_helper_codes_table.php`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `helper_code` | string(20), **PK**, non-increment | |
| `entity_name` | string(100) | Nama entitas (pelanggan/vendor/karyawan) |
| `marketing_name` | string(50) nullable | |
| `normal_balance` | enum(DEBET, KREDIT) | |
| timestamps | | |

## 2. Tabel Transaksi Pembelian (Purchase)

### 2.1 `purchase_orders` — Key prefix `PO`
Migration: `2026_05_28_024105` + `2026_06_05_083603` (kolom pajak)

Kolom inti (dari pemakaian kode): `id (PK)`, `po_number` (unique, format `PO-...`), `contact_name`, `status` (DRAFT/APPROVED/PARTIAL/RECEIVED), tanggal, kolom pajak tambahan (`2026_06_05`), relasi `details`.

### 2.2 `purchase_order_details`
Migration: `2026_05_28_024106`
Kolom: `id`, `purchase_order_id (FK)`, `item_code`, `price`, `qty`, `qty_received`.

### 2.3 `purchase_bills` — Key prefix `BIL`
`PurchaseBill` model: `$guarded = []` (⚠️ lihat RULES.md — risiko mass assignment). Kolom inti: `bill_number` (format `BIL-YYYYMMDD-XXXX`), referensi PO, supplier, due date, total.

### 2.4 `purchase_bill_details`
`$guarded = []`. Relasi ke `purchase_bill_id`, `account_code` (⚠️ akun langsung di detail — pola berbeda dari invoice yang menghitung by-formula).

### 2.5 `purchase_returns` — Key prefix `PR`
Migration: `2026_06_22_035351_create_purchase_returns_table.php`. Kolom inti: `return_number` (format `PR-YYYYMMDD-XXXX`), referensi PO asal.

### 2.6 `purchase_return_details`
Migration: sama file di atas.

## 3. Tabel Transaksi Penjualan (Sales)

### 3.1 `sales_orders` — Key prefix `SO` (dan varian `POS`)
Migration: `2026_06_08_035003` + `2026_06_08_040325` (advanced fields) + `2026_06_29_092233` (wms_status) + `2026_07_23_000001` (invoice_ref)

Kolom inti: `id`, `so_number` (format `SO-YYMMDD-####`), `contact_name`, `store_name`, `status` (DRAFT/APPROVED/SHIPPED), `other_discount`, `invoice_id`, `invoice_no`, `wms_status`.

### 3.2 `sales_order_details`
Migration: `2026_06_08_035005`. Kolom: `item_code`, `qty`, `price`.

### 3.3 `sales_invoices` — Key prefix `INV`
Migration: `2026_06_08_091245_create_sales_invoices_table.php`

Kolom inti: `id`, `invoice_number` (format `INV-YYMMDD-####`), `sales_order_id (FK)`, `transaction_date`, `contact_name`, `sub_total`, `disc_amount`, `tax_amount`, `shipping_cost`, `grand_total`, `payment_status` (UNPAID/...).

### 3.4 `sales_invoice_details`
Migration: `2026_06_08_091246`. Kolom: `sales_invoice_id`, `product_id`, `item_code`, `description`, `price`, `qty_actual`, `disc_amount`, `amount`, `is_substitution`.

### 3.5 `sales_returns` — Key prefix `SR`
Migration: `2026_06_20_083759_create_sales_returns_table.php`. `return_number` (format `SR-YYYYMMDD-XXXX`).

### 3.6 `sales_return_details`
Migration: `2026_06_20_083805`.

### 3.7 Kolom lintas-tabel `tags`
Migration `2026_06_09_090849_add_tags_to_all_transactions.php` — menambahkan kolom `tags` ke seluruh tabel transaksi (untuk pelaporan channel/toko).

## 4. Tabel Inventori

### 4.1 `products`
Migration: `2026_05_28_024103_create_products_table.php`

| Kolom | Keterangan |
|---|---|
| `id` | PK |
| `sku` | Kode barang, dipakai sebagai kunci lookup di Service (`whereIn('sku', ...)`) |
| `name`, `variation`, `category_name`, `unit` | Master data |
| `sell_price` | Harga jual |
| `average_cost` | **HPP Moving Average** — di-update tiap penerimaan barang |
| `stock_quantity` | Stok berjalan |
| `inventory_account_code`, `cogs_account_code` | Mapping akun COA per produk (persediaan & HPP) |

### 4.2 `inventory_ledgers` (Kartu Stok) — Key prefix mengikuti `evidence_number` sumber (INV/BIL/PO/dsb.)
Migration: `2026_05_28_024107_create_inventory_ledgers_table.php`

| Kolom | Keterangan |
|---|---|
| `id` | PK |
| `transaction_date` | |
| `evidence_number` | Nomor bukti transaksi sumber (INV-xxx / Penerimaan PO / dsb.) |
| `product_id (FK)` | |
| `type` | enum: `IN`, `OUT`, `ADJ` |
| `qty` | Jumlah mutasi |
| `unit_cost`, `total_cost` | Harga modal saat transaksi |
| `running_qty`, `running_value`, `moving_average_cost` | Saldo kumulatif untuk valuasi persediaan |
| `description` | |

## 5. Tabel Aset Tetap

### 5.1 `assets`
Migration: `2026_05_09_064015` + `2026_07_24_000001` (asset fields) + `2026_07_24_091049` (residual_value) + `2026_07_24_094900` (journal_detail_id nullable)

| Kolom | Keterangan |
|---|---|
| `journal_detail_id` | nullable, FK → `journal_details.id` (link ke pembelian aset di jurnal) |
| `asset_code`, `asset_name`, `category` | Master aset |
| `quantity` | |
| `purchase_date`, `purchase_price` | decimal:2 |
| `residual_value` | decimal:2 — nilai residu untuk depresiasi garis lurus |
| `useful_life_months` | integer |
| `is_active` | boolean |

## 6. Tabel Payment Plan (Kas Kecil/Bank/Reimburse)

### 6.1 `master_divisi`
Migration: `2026_05_15_073048_create_master_divisi_table.php`. Kolom: `id_divisi (PK)`, `kode_divisi`, `nama_divisi`, `status_aktif`.

### 6.2 `transaksi_payment_plan`
Migration: `2026_05_15_081700` + `2026_05_16_041425` (id_akun) + `2026_05_16_071157` (kategori_payment) + `2026_06_06_024402` (bukti_file) + `2026_07_20_100000` (payment_categories table)

| Kolom | Keterangan |
|---|---|
| `id_payment` | PK |
| `no_transaksi` | unique — format khusus (bukan prefix alfabetik), lihat §"KEY Payment Plan" di RULES.md |
| `id_divisi (FK)` | → `master_divisi` |
| `tgl_pengajuan`, `jatuh_tempo` | |
| `jenis_transaksi` | KAS KECIL / KAS BESAR / BANK |
| `vendor_toko`, `penerima_pj`, `rekening_va` | |
| `keterangan` | text |
| `nominal` | decimal(15,2) |
| `status_payment` | PENGAJUAN → APPROVED → PAID |
| `id_akun` | ditambahkan — mapping ke COA |
| `kategori_payment` | ditambahkan — FK logis ke `payment_categories` |
| `bukti_file` | ditambahkan — lampiran bukti bayar |

### 6.3 `payment_categories`
Migration: `2026_07_20_100000_create_payment_categories_table.php`. Model `PaymentCategory`.

## 7. Tabel Pendukung

| Tabel | Migration | Keterangan |
|---|---|---|
| `taxes` | `2026_06_05_064707_create_taxes_table.php` | Master pajak |
| `company_profiles` | `2026_07_07_000000` + `..._000001` (logo) + `..._000002` (employee_pin) | Profil perusahaan, PIN karyawan (untuk portal publik payment plan?) |
| `system_logs` | `2026_06_19_033525_create_system_logs_table.php` | Audit trail aksi user |
| `personal_access_tokens` | `2026_06_11_045852` | Laravel Sanctum (API token) |
| `temp_journal_tables` (staging) | `2026_06_15_071710_create_temp_journal_tables.php` | Tabel sementara untuk proses sinkronisasi [External Platform] async via Job |
| `users`, `cache`, `jobs` | `0001_01_01_*` (bawaan Laravel) | |

## 8. Ringkasan Tipe Primary Key Non-Standar (WAJIB DIPATUHI — lihat RULES.md)

| Tabel | Primary Key | Tipe |
|---|---|---|
| `accounts` | `account_code` | string, non-increment |
| `helper_codes` | `helper_code` | string, non-increment |
| `journal_headers` | `journal_id` | string, non-increment |
| `journal_details` | `id` **[inferred]** | integer, auto-increment |
| Semua tabel transaksi lain (PO, SO, Invoice, Bill, Return, Product, Asset) | `id` | integer, auto-increment (standar Laravel) |

## 9. Rekomendasi Tindak Lanjut Skema

1. **Audit `information_schema` produksi** untuk tabel `journal_headers` dan `journal_details` guna memastikan kolom `evidence_number`, `id`, `position`, `amount` benar-benar ada — lalu buat migration "catch-up" resmi agar riwayat migration konsisten dengan skema aktual (hindari kegagalan `php artisan migrate:fresh` di lingkungan baru).
2. Pertimbangkan menambahkan **foreign key formal** `journal_headers.evidence_number` → tabel dokumen sumber (via kolom terpisah per jenis dokumen, atau tabel polymorphic `document_type` + `document_id`) agar integritas rujukan terjamin oleh database, bukan hanya oleh disiplin penulisan string di kode aplikasi.
3. Buat Model Eloquent resmi untuk `transaksi_payment_plan` (`PaymentPlan`) dan `MasterDivisi` agar konsisten dengan pola modul lain.

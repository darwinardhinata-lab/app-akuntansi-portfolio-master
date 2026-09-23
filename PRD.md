# PRD.md — Product Requirements Document: ERP Akuntansi

> Status: **BARU** (disusun retroaktif dari kode yang sudah berjalan — *reverse-engineered PRD*, bukan dokumen perencanaan awal). Tujuannya menjadi rujukan tunggal "apa yang seharusnya dilakukan sistem ini" berdasarkan perilaku aktual kode per 25 Juli 2026.

## 1. Latar Belakang & Tujuan Produk

Perusahaan menjalankan bisnis multi-channel (marketplace/Tokopedia dkk, toko fisik beberapa cabang — lihat data seed `master_divisi`: DESAIN & KREATIF, MP, LIVE, Toko Pakel, Toko Mojosongo, Toko Pajang, Toko Banguntapan, CS, IT) yang operasional harian dikelola di **[External Platform]** (platform commerce pihak ketiga). Sistem ini adalah **ERP Akuntansi internal** yang:
1. Menyerap data transaksi dari [External Platform] (jurnal, PO, SO, dsb.) via impor CSV/webhook.
2. Menyediakan modul transaksi akuntansi **native** (PO, SO, Invoice, Bill, Retur, Jurnal manual) untuk kasus yang tidak sepenuhnya tercakup [External Platform], atau untuk transaksi internal murni (Payment Plan/kas kecil).
3. Menghasilkan laporan keuangan standar (Laba Rugi, Neraca, Arus Kas, Buku Besar) yang akurat dan bisa diaudit.
4. Mengelola aset tetap dan depresiasinya.
5. Mengelola pengajuan & pembayaran kas kecil/bank karyawan lintas divisi dengan alur approval.

## 2. Target Pengguna

| Peran | Kebutuhan Utama |
|---|---|
| Staf Akuntansi/Finance | Input jurnal manual, rekonsiliasi data [External Platform], cetak laporan keuangan |
| Staf Gudang/Warehouse | Input penerimaan barang (inbound), pengeluaran barang (outbound), retur |
| Staf Penjualan/CS | Buat Sales Order, proses pengiriman/invoice |
| Staf Pembelian | Buat Purchase Order, terima barang, catat tagihan |
| Karyawan Umum (semua divisi) | Ajukan pengeluaran kas kecil/reimburse via portal publik tanpa login |
| Manajemen/Owner | Melihat Dashboard, Laporan Laba Rugi/Neraca/Arus Kas, Budgeting/Forecast |
| Admin Sistem | Kelola user, COA, kode bantu, divisi, kategori payment, profil perusahaan |

## 3. Ruang Lingkup Fitur (Functional Scope)

### 3.1 Modul yang SUDAH Diimplementasikan Penuh
- **Autentikasi & Otorisasi** — login, logout, manajemen user.
- **Chart of Accounts (COA)** — CRUD akun, saldo awal, import/export Excel, larangan hapus akun bersaldo/berelasi.
- **Kode Bantu (Sub-ledger Tags)** — CRUD, import, untuk melacak transaksi per entitas (customer/vendor/karyawan) di luar COA utama.
- **Jurnal Umum** — CRUD manual, import CSV massal (dengan deteksi format tanggal/angka otomatis), export, audit balance via command `jurnal:check-balance`.
- **Buku Besar** — tampilan mutasi per akun.
- **Laporan Keuangan** — Laba Rugi (single & multi-periode/matrix), Neraca, Arus Kas, dengan klasifikasi akun dinamis berbasis `normal_balance`.
- **Laporan Lanjutan** — Kronologi HPP (`cogs-chronology`), Laporan per Tag, AR/AP Aging & Down Payment (`ar-dp`, `ap-dp`), AR/AP Sub-ledger management.
- **Purchase Order → Penerimaan Barang (partial/full) → Tagihan (Bill)** — dengan penguncian baris, validasi qty, kalkulasi Moving Average Cost, posting jurnal otomatis, dan pembatalan (`void`).
- **Sales Order → Pengiriman (Shipment) → Invoice** — dengan validasi ulang total server-side, pengecekan stok, posting jurnal otomatis (Piutang/Penjualan/HPP/Persediaan), pembatalan (`void`).
- **Retur Penjualan & Retur Pembelian** — proses pemeriksaan gudang, penyesuaian stok & jurnal.
- **Purchase Bill** — pencatatan tagihan pembelian terhubung ke PO.
- **Master Barang (Produk)** — CRUD, import/export, kartu stok (inventory ledger), harga jual & HPP.
- **Warehouse** — proses inbound/outbound terpisah dari alur PO/SO standar (untuk kasus manual).
- **Aset Tetap** — CRUD, import/export, generate depresiasi, toggle status aktif, cleanup data yatim (`asset:cleanup-orphan`).
- **Payment Plan (Kas Kecil/Bank)** — pengajuan (termasuk via portal publik tanpa login), approval, set COA & rekening tujuan, posting jurnal, export, import CSV, dapat memicu pembuatan PO (uang muka pembelian).
- **Budgeting & Forecast** — 8 metode peramalan (SMA, WMA, Exponential Smoothing, Double/Triple Exponential Smoothing/Holt-Winters, Linear Regression, Median Forecast, Seasonal Naive) berbasis data histori jurnal.
- **Dashboard** — ringkasan visual + chart data via endpoint AJAX.
- **Master Data Pendukung** — Divisi, Pajak (Tax), Kategori Payment, Profil Perusahaan (termasuk PIN karyawan & logo).
- **Smart Document Tracing** — navigasi lintas modul dari satu nomor bukti (lihat RULES.md §8).
- **Audit/System Log** — pencatatan aktivitas user per entitas.
- **Integrasi [External Platform]** — webhook penjualan real-time + command sinkronisasi/impor massal terjadwal (fast import/sync untuk jurnal, PO, produk, histori penjualan).
- **Multi-bahasa (ID/EN)**.

### 3.2 Modul yang DIRUJUK tapi BELUM Diimplementasikan Penuh
- **Manufaktur (SPK/MFG)** — `DocumentTraceController` sudah menyediakan routing (`mfg.spk.index`) namun controller/model/migration modul ini **tidak ditemukan** dalam snapshot kode saat ini. Berstatus **fitur direncanakan, belum dibangun**.

## 4. Alur Bisnis Kunci (Key User Journeys)

### 4.1 Siklus Pembelian
1. Staf Pembelian membuat **PO** ke supplier.
2. Barang tiba di gudang → staf gudang input **Penerimaan** (bisa partial) → sistem otomatis: update stok & HPP rata-rata, catat kartu stok, **posting jurnal** (Persediaan Dr / Hutang atau Uang Muka Cr).
3. Tagihan resmi dari supplier dicatat sebagai **Bill** terhubung ke PO.
4. Jika ada kesalahan barang, staf memproses **Retur Pembelian**.
5. Jika penerimaan salah input, staf dapat **void** penerimaan (mengembalikan stok & menghapus jurnal).

### 4.2 Siklus Penjualan
1. Staf Penjualan/CS membuat **SO** (manual, impor, atau dari sinkronisasi channel/[External Platform], termasuk kanal **POS**).
2. Saat barang dikirim, sistem membuat **Invoice** otomatis: validasi ulang total, cek & kurangi stok, **posting jurnal** (Piutang Dr / Penjualan Cr / HPP Dr / Persediaan Cr, plus pos diskon/ongkir/pajak sesuai mapping COA).
3. Jika pelanggan mengembalikan barang, staf memproses **Retur Penjualan**.
4. Pengiriman yang salah dapat di-**void**.

### 4.3 Siklus Kas Kecil/Bank (Payment Plan)
1. Karyawan mengajukan lewat **portal publik** (`/form-pengajuan`, tanpa login, dengan rate-limit) atau staf internal input langsung.
2. Sistem generate `no_transaksi` unik berbasis divisi+jenis+tanggal+urutan (dengan retry anti-race-condition).
3. Approval → set akun COA & rekening tujuan → **posting jurnal**.
4. Untuk kategori "Pembelian Persediaan (Uang Muka)", transaksi ini terhubung ke pembuatan **PO** dan mempengaruhi pemilihan akun kredit saat penerimaan barang nanti (Uang Muka vs Hutang Usaha biasa).

### 4.4 Siklus Pelaporan
1. Semua transaksi (PO/SO/Invoice/Bill/Retur/Payment Plan/Aset/Jurnal manual/Impor [External Platform]) bermuara ke `journal_headers`+`journal_details`.
2. Laporan Laba Rugi, Neraca, Arus Kas, Buku Besar, dan laporan lanjutan (AR/AP, Tag, COGS) semuanya membaca dari sumber tunggal ini — **satu sumber kebenaran (single source of truth) untuk angka keuangan.**
3. Budgeting membaca sumber yang sama untuk proyeksi (namun dengan logika klasifikasi akun yang sedikit berbeda — lihat RULES.md §7.5, perlu diselaraskan).

### 4.5 Siklus Sinkronisasi [External Platform]
1. Data masuk via webhook real-time (penjualan) atau command terjadwal/manual (jurnal, PO, produk, histori).
2. Data besar masuk ke tabel staging (`temp_*`), diproses Job di background agar tidak membebani request HTTP.
3. Dashboard menyimpan cache hasil sinkronisasi lewat Job terpisah per modul (Bill/Inv/PO/SO) agar loading cepat.

## 5. Aturan Bisnis Non-Negotiable (Ringkasan — detail penuh di RULES.md)

- Setiap jurnal harus balance Debit = Kredit (toleransi 0, dibulatkan 2 desimal).
- Stok tidak boleh negatif.
- Akun/kode bantu yang sudah bertransaksi tidak boleh dihapus.
- Setiap transaksi yang menyentuh >1 tabel harus atomik (DB transaction).
- Setiap operasi yang bisa dijalankan paralel (penerimaan PO, shipment SO, generate nomor) harus dilindungi row-lock.

## 6. Metrik & Pelaporan yang Didukung

- Laba Rugi (P&L) — single period & multi-period matrix.
- Neraca (Balance Sheet).
- Arus Kas (Cash Flow) — dikelompokkan Operasi/Investasi/Pendanaan berdasarkan prefix akun.
- Buku Besar per akun.
- Kronologi HPP.
- Laporan per Tag (channel/toko).
- AR/AP Aging & Down Payment.
- AR/AP Sub-ledger (manajemen piutang/hutang per pelanggan/vendor via Kode Bantu).
- Kartu Stok per produk.
- Proyeksi/Forecast Budgeting (8 metode).
- Dashboard ringkasan real-time (chart data via AJAX).

## 7. Batasan & Non-Tujuan (Out of Scope Saat Ini)

- Modul Manufaktur/Produksi penuh (baru placeholder routing).
- Tidak ada indikasi modul Payroll/HRD dalam kode ini.
- Tidak ada indikasi multi-currency (semua kalkulasi asumsi Rupiah, format `NumberParser`/`formatCurrency` khusus Rupiah).
- Tidak ada indikasi multi-tenant/multi-perusahaan (satu `company_profiles` tunggal per instalasi, bukan tabel jamak berelasi user).

## 8. Kesenjangan Kualitas yang Perlu Masuk Roadmap Perbaikan

(Detail teknis lengkap: ARCHITECTURE.md §7 dan RULES.md §7)
1. Sinkronkan logika klasifikasi akun 7–9 antara `BudgetingService` dan `ProfitLossController`.
2. Perbaiki `$guarded = []` pada model transaksi pembelian (`PurchaseBill`, `PurchaseBillDetail`, `PurchaseReturn`).
3. Sentralisasi mapping `account_code` hardcoded ke satu file konfigurasi.
4. Rekonsiliasi migration vs skema produksi aktual (`journal_headers.evidence_number`, kolom `journal_details.id/position/amount`).
5. Standarisasi strategi penomoran dokumen ke pola row-lock sequence (saat ini `BIL-`/`PR-`/`SR-` masih memakai `uniqid()`/counter tanpa lock).
6. Verifikasi mekanisme keamanan webhook [External Platform] (di luar CSRF, perlu signature/secret token).
7. Bangun Model Eloquent resmi untuk `transaksi_payment_plan` dan `master_divisi`.

## 9. Definisi Sukses Produk

Sistem dianggap berjalan sesuai tujuan jika:
- Semua laporan keuangan (Laba Rugi/Neraca/Arus Kas) menghasilkan angka yang konsisten satu sama lain karena berasal dari sumber jurnal tunggal yang sama.
- Tidak ada jurnal yang tidak balance lolos ke Buku Besar (dijamin oleh guard di 3 titik input: manual, import, dan otomatis dari transaksi).
- Setiap nomor dokumen dapat ditelusuri lintas modul dalam satu klik (`DocumentTraceController`).
- Operasi impor data besar dari [External Platform] tidak membuat sistem timeout/kehabisan memori.

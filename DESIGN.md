# DESIGN.md — Panduan Desain Teknis & Antarmuka ERP Akuntansi

> Status: **BARU** (belum ada dokumen desain terpisah sebelumnya). Disusun dari observasi 70 file Blade view, pola Controller/Service, dan konvensi penamaan yang konsisten di seluruh kode.

## 1. Filosofi Desain

Sistem dirancang sebagai **ERP internal berbasis form + tabel**, dioptimalkan untuk:
- Input transaksi cepat oleh staf akuntansi/gudang (bukan customer-facing, kecuali portal Payment Plan publik).
- Volume data besar (impor CSV ratusan ribu baris dari [External Platform]) → UI harus tetap responsif meski data besar (pagination, AJAX partial-load, background Job).
- Akurasi angka mutlak di atas estetika — validasi ketat di server, bukan hanya client-side.

## 2. Pola UI (Blade + AJAX)

### 2.1 Struktur Layout
- `resources/views/layouts/app.blade.php` — layout master (sidebar navigasi modul, breadcrumb).
- `resources/views/components/breadcrumb.blade.php` + `App\View\Components\Breadcrumb` — komponen breadcrumb reusable dipakai di semua halaman modul.
- Pola per-modul konsisten: `index.blade.php` (list/tabel + filter), `create.blade.php`, `edit.blade.php`, kadang `show.blade.php` (detail read-only, mis. `sales_invoice/show.blade.php`, `purchase_bill/show.blade.php`).

### 2.2 Pola AJAX Partial
- `journal/partials/ajax_detail.blade.php` — detail baris jurnal dimuat via AJAX (`/jurnal/detail/ajax`) tanpa reload halaman penuh — penting untuk performa saat daftar jurnal besar.
- `system_log/partials/ajax_list.blade.php` — log aktivitas dimuat AJAX (`/system-logs/entity-ajax`).
- Pola ini **direkomendasikan untuk fitur baru** yang menampilkan data anak (child rows) dari daftar besar, agar tidak perlu render semua detail di initial page load.

### 2.3 Form Transaksi Multi-Baris (Header-Detail)
Semua form transaksi (`journal/create`, `purchase_order/create`, `sales_order/create`, dll.) mengikuti pola **header + repeatable detail rows**:
- Header: tanggal, nomor otomatis (read-only/preview), pihak terkait (`contact_name`).
- Detail: baris item/akun yang bisa ditambah/hapus dinamis via JS, dengan total dihitung ulang di client (untuk UX) **namun WAJIB divalidasi ulang di server** (lihat `SalesOrderService::createInvoiceAndShip()` — pola "C5 FIX" menghitung ulang subtotal dari `actualItems` dan membandingkan dengan yang dikirim client, toleransi 0.01).
- **Aturan desain**: setiap form baru dengan pola ini **wajib** mengikuti pola validasi ulang server-side yang sama — jangan percaya total yang dihitung di JavaScript.

### 2.4 Nomor Dokumen: Preview vs Final
Beberapa form menampilkan nomor dokumen "sementara" untuk preview UI (`SalesOrderController` — komentar "Generate nomor SO sementara untuk display, bisa diedit user"), namun nomor final tetap di-generate ulang di server saat `store()`/Service dipanggil, untuk menghindari race condition antara preview dan submit aktual. **Pola desain**: JANGAN pernah menganggap nomor yang tampil di form sebagai nomor final yang pasti tersimpan — selalu re-generate atau re-validate keunikan di server.

## 3. Konvensi Penamaan (Naming Conventions)

| Elemen | Konvensi | Contoh |
|---|---|---|
| Route name | `modul.aksi` (dot notation, prefix modul disingkat) | `po.edit`, `so.ship`, `invoice.show`, `jurnal.store` |
| Nomor dokumen | `{KEY}-{tanggal}-{sequence/random}` | `INV-260725-0001`, `SO-260725-4821` |
| Kolom status | Enum string uppercase | `DRAFT`, `APPROVED`, `SHIPPED`, `RECEIVED`, `PARTIAL`, `PENGAJUAN`, `PAID` |
| Kolom posisi jurnal | `DEBET` / `KREDIT` (Bahasa Indonesia, bukan DEBIT/CREDIT) | konsisten di seluruh kode |
| Nama Service method aksi bisnis | Kata kerja + objek | `createInvoiceAndShip()`, `receivePartialOrder()`, `voidShipment()`, `voidReceipt()`, `generateNoTransaksi()` |
| Migration timestamp | Format Laravel standar `YYYY_MM_DD_HHMMSS_deskripsi.php` | — |

## 4. Pola Penamaan Fungsi "Void" (Pembatalan)

Setiap aksi yang men-generate jurnal + mutasi stok **wajib** memiliki fungsi kebalikan bernama `void*()`:
- `SalesOrderService::voidShipment()` ↔ `createInvoiceAndShip()`
- `PurchaseOrderService::voidReceipt()` ↔ `receivePartialOrder()`

Pola `void*()` **wajib**:
1. Hapus jurnal terkait (`journal_headers`/`journal_details` where `evidence_number`).
2. Kembalikan stok (`InventoryLedger` + update `Product.stock_quantity`/`average_cost`).
3. Kembalikan status header ke kondisi sebelumnya (mis. `SHIPPED → APPROVED`).
4. Dibungkus dalam satu `DB::transaction`.

**Setiap fitur baru dengan efek jurnal+stok otomatis WAJIB mengikuti pola `void*()` yang simetris ini** — agar user selalu punya jalan pembatalan yang aman dan konsisten.

## 5. Desain Penomoran Dokumen (Numbering Strategy)

Tiga strategi penomoran berbeda ditemukan di kode — **gunakan strategi #3 untuk fitur baru** (paling aman terhadap race condition):

1. **Random-based** (rawan duplikat, dipakai di beberapa tempat lama): `rand(1000,9999)` atau `substr(uniqid(), -4)` — mis. `BIL-`, `PR-`. Kelemahan: peluang tabrakan kecil tapi nyata pada volume tinggi.
2. **Count-based tanpa lock** (`SR-` — `$lastNumber + 1`): rawan race condition jika dua request submit bersamaan.
3. **Sequence-based dengan row lock** (direkomendasikan, dipakai di `JournalHeader::generateNextId()` dan `SalesOrderService` versi terbaru untuk `INV-`): query nomor terakhir dengan `lockForUpdate()` di dalam `DB::transaction()`, lalu `+1`. Ini pola yang **sudah diperbaiki** ("FIX: Gunakan sequence dari DB — rand() berisiko duplikat") dan harus jadi standar baru.

**Rekomendasi desain ke depan**: migrasikan `BIL-`, `PR-`, `SR-` ke pola row-lock sequence yang sama dengan `INV-`/`JRN-`, demi konsistensi dan mencegah *duplicate number* di beban tinggi (lihat juga `BUG-01` di audit internal — "Tabrakan Primary Key pada Posting Payment Plan").

## 6. Desain Laporan Keuangan

Struktur laporan (`ProfitLossController`, `BalanceSheetController`, `CashFlowController`) mengikuti pola:
1. Query agregasi `journal_details` join `journal_headers` join `accounts`, grup per akun.
2. Klasifikasi akun ke kelompok laporan berdasarkan `account_code` prefix **dan** `accounts.normal_balance` (bukan prefix semata — lihat RULES.md §2).
3. Dua mode output: **struktur tunggal** (`generateStructure()`) untuk satu periode, dan **struktur matriks** (`generateMatrixStructure()`) untuk perbandingan multi-periode (kolom per bulan/kuartal).
4. **Pola desain untuk laporan baru**: selalu sediakan kedua mode ini jika relevan (single-period untuk cetak/export, matrix untuk analisis tren), dan selalu delegasikan logika pengelompokan akun ke satu fungsi `determineAccountGroup()` yang dipakai bersama — **jangan** duplikasi logika klasifikasi akun ke laporan baru (ini sudah jadi masalah nyata dengan `BudgetingService`, lihat RULES.md §7.5).

## 7. Ekspor Data (Excel)

Semua modul memiliki kelas Export terpisah di `app/Exports/` (`AccountExport`, `JournalExport`, `SalesInvoiceExport`, dll.), memakai interface `maatwebsite/excel` (`FromQuery`/`FromCollection`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`). `JournalExport` juga memakai `WithBatchInserts, WithChunkReading` untuk volume besar. **Standar untuk Export baru**: gunakan `FromQuery` + `WithChunkReading` bila potensi baris > 10.000, agar tidak menghabiskan memori.

## 8. Import Data (Excel/CSV)

Pola `app/Imports/` konsisten memakai `maatwebsite/excel` `ToModel`/`ToCollection` dengan validasi baris + pelaporan baris gagal (`failedParseCount`). Untuk file sangat besar (CSV jurnal [External Platform]), dipakai **custom streaming parser** (`fgets()` manual) di `JournalCsvImportService`, bukan `maatwebsite/excel`, karena kebutuhan kontrol memori lebih granular dan auto-deteksi delimiter (`;` vs `,`) serta format tanggal/angka Indonesia vs US (lihat `NumberParser`).

## 9. Internasionalisasi (i18n)

`LanguageController` + `SetLocaleMiddleware` menyediakan switch bahasa ID/EN via `/lang/{locale}`. Seluruh label bisnis inti (status, deskripsi jurnal) tetap dalam **Bahasa Indonesia** di level data (karena mengikuti istilah akuntansi Indonesia baku: DEBET/KREDIT, Neraca, Laba Rugi) — i18n hanya berlaku untuk **label UI**, bukan data transaksi tersimpan.

## 10. Prinsip Desain untuk Perubahan Selanjutnya

1. **Jangan pecah "detak jantung" akuntansi** (lihat RULES.md §6) — perubahan UI/UX form boleh sebebas mungkin, tapi alur commit Service (transaction + lock + validasi balance + void simetris) harus tetap utuh.
2. **Konsistensi dulu, fitur baru kemudian** — sebelum menambah modul baru (mis. Manufaktur penuh), selesaikan dulu inkonsistensi yang sudah teridentifikasi (`$guarded=[]`, hardcoded COA, BudgetingService prefix statis) agar fondasi tidak makin retak.
3. **Setiap KEY/prefix dokumen baru = satu baris baru di `DocumentTraceController` dan satu baris baru di RULES.md §8** — tanpa kecuali.

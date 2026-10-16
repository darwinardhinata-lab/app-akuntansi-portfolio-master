# Rencana Fitur Multicurrency — app-akuntansi

Basis analisa: branch `feat/marvel-mgi-accounting-safeguards` (zip 964 file, 708 file PHP).
Tanggal: 2026-10-09.

## 0. Cakupan & batasan analisa (jujur)

- **Dibaca penuh:** `PostingService`, `JournalAmount`, `JournalBalanceValidator`, `JournalHeader`, `JournalDetail`, `JournalHeaderBuilder`, `JournalDetailBuilder`, `NumberParser`, `DocumentSequence`, `Account`, `Company`, `PurchaseOrderService`, `config/coa.php`, `config/platform.php`, migrasi jurnal/PO/SO/Invoice/Bill/Payment Plan/Companies/Party bank.
- **Dipindai dengan grep/statistik (tidak dibaca baris per baris):** `SalesOrderService`, controller laporan (Neraca, Laba Rugi, Buku Besar, Dashboard), `JournalController`, modul Customs, view Blade, seluruh pemanggil `PostingService`.
- **Tidak dibaca:** modul Manufacturing (service/controller/view), `PaymentPlanService`/`PaymentPlanController`, `GrnReceivingService`, `BudgetingService`, `AssetDepreciationService`, isi test. Fase yang menyentuh area ini di bawah **wajib diawali audit oleh Cline** (lihat prompt).
- Tidak ada kode yang dijalankan (tidak ada `composer install`/`phpunit`). Semua temuan berbasis pembacaan kode.

---

## 1. Temuan audit (urut tingkat keparahan, relevan untuk multicurrency)

### KRITIS — penghalang langsung

| # | Temuan | Bukti | Dampak finansial |
|---|--------|-------|------------------|
| K1 | Tidak ada konsep mata uang di jurnal & dokumen. `journal_details.amount` tunggal tanpa kode mata uang/kurs. `PRD.md:112` sendiri menyatakan "semua kalkulasi asumsi Rupiah". | `journal_details`, `journal_headers`, `PostingService` | Transaksi USD hanya bisa dicatat dengan konversi manual di luar sistem → kurs tidak terlacak, tidak bisa direvaluasi, tidak bisa diaudit. |
| K2 | Perhitungan uang memakai **float PHP**, bukan bcmath: `$qtyTerimaSkrg * $detail->price`, `(float) $financials[...]`, `(int) $item['qty']` (qty pecahan terpotong). Validator jurnal memakai bcmath 2 desimal, tapi masukannya sudah float. | `PurchaseOrderService::receiveLegacy`, `SalesOrderService::createInvoiceAndShip` | Dengan kurs berdesimal, galat float menumpuk → jurnal "tidak balance" acak atau selisih sen tersembunyi di persediaan/HPP. |
| K3 | `JournalDetail::insert()` massal dipakai ±27 file (Warehouse, PaymentPlan, Manufacturing, Import, FastImport*, dst.) dengan array kolom tetap. Menambah kolom wajib tanpa default akan merusak semua posting. | `PostingService::post` + `grep` pemanggil | Migrasi harus 100% aditif (nullable/default), jika tidak seluruh posting gagal. |
| K4 | Tidak ada pelunasan/alokasi pembayaran ke tagihan; `docs/MARVEL_GAP_PURCHASE_TO_PAYMENT.md:68` mencatat partial payment, **mata uang dan kurs** sebagai gap. | Dokumen gap + skema `transaksi_payment_plan` (`nominal decimal(15,2)`) | Tanpa alokasi, laba/rugi selisih kurs **terealisasi** tidak bisa dihitung. |
| K5 | Akun kas/bank hanya IDR: `PaymentFundingAccount::IDR_CODES` di-hardcode; tabel `accounts` tidak punya kolom mata uang. | `app/Support/PaymentFundingAccount.php`, migrasi `accounts` | Tidak ada rekening USD/CNY, tidak ada dasar revaluasi akun moneter. |

### TINGGI

| # | Temuan | Bukti |
|---|--------|-------|
| T1 | `journal_headers` **tidak punya `company_id`**, padahal `companies.base_currency` ada dan PO/SO sudah ber-`company_id`. `base_currency` di-hardcode `'IDR'` di `MgiFreshStartPolicy`. Multi-company dengan mata uang dasar berbeda belum aman. |
| T2 | `PurchaseOrderService::voidReceipt` **menghapus permanen** jurnal (via `ProtectedJournalQuery`). Untuk transaksi valas, penghapusan merusak jejak kurs & selisih kurs; harus diganti reversal. |
| T3 | Format "Rp" hardcode: ±201 baris di ±40 view Blade, 309 pemakaian `number_format`, `NumberParser::formatCurrency()`, dan pesan error `PostingService`. |
| T4 | Laporan memakai `SUM(amount)` mentah di ±20 file (Dashboard 11, Neraca 9, Mfg report 9, AdvancedReport 8, Buku Besar 4, Laba Rugi 3, Arus Kas 3, Budgeting 3). **Aman selama `amount` tetap = nilai IDR** (lihat keputusan D1). Jika semantik `amount` diubah, semua laporan salah. |
| T5 | `JournalAmount::normalize()` memaksa maks. 2 desimal & parser lokal ID. Tidak cocok untuk kurs (6–8 desimal) maupun mata uang 0 desimal (JPY). |

### SEDANG

| # | Temuan |
|---|--------|
| S1 | Customs: `cst_customs_documents.exchange_rate` `decimal(20,4)`, tetapi form `customs/edit` mengizinkan `step="0.000001"` → pembulatan diam-diam. Kolom kurs ada tetapi tidak terhubung ke jurnal. `cbr_dokumen_pabean_lines.mata_uang` hanya `string(3)` tanpa master. |
| S2 | `journal_headers.debit/credit` dan `journal_details.debit/credit` (legacy) bertipe `float(16,4)`. Belum diverifikasi apakah masih dibaca laporan — **jangan dipakai untuk nilai valas**. |
| S3 | `SalesOrderService` memakai toleransi `0.01` float pada pembandingan total. |
| S4 | Higiene repo: file di root (`check_*.php`, `create_erp_local_user.php`, `set_admin_role.php`, `query.php`, `d`, `details`, `FIX_DOUBLE`, `composer.phar`); file migrasi malformed `2026_08_04_000002_add_payment_` masih ada di zip; `PRD.md` belum mencatat rencana multicurrency. |

**Catatan positif:** `.gitignore` sekarang sudah benar (`.env` diabaikan); pola pengaman sudah baik (feature flag `platform.*`, `lockForUpdate`, `DocumentSequence::generateSecure`, proteksi jurnal Maklun/GRN, `OperationalDatabaseSafety`). Rencana ini mengikuti pola tersebut.

---

## 2. Keputusan desain (**perlu persetujuan sebelum Fase 1**)

| ID | Keputusan | Alasan |
|----|-----------|--------|
| D1 | `journal_details.amount` **tetap = nilai mata uang dasar (IDR)**. Tambah kolom `currency_code`, `amount_txn`, `exchange_rate`. | Semua laporan `SUM(amount)` (T4) tidak berubah → risiko regresi terkecil. |
| D1b | Kolom dokumen existing (`sub_total`, `grand_total`, dst.) **tetap IDR**. Tambah `currency_code`, `exchange_rate`, `*_txn` untuk nilai valas. | Dashboard/export yang menjumlah `grand_total` tetap benar. |
| D2 | **Satu mata uang per dokumen.** Baris jurnal membawa snapshot kurs. Baris selisih kurs hanya bernilai IDR (`amount_txn` = NULL). | Sederhana, cukup untuk 95% kasus ekspor/impor. |
| D3 | Kurs = "1 unit valas = X IDR", `decimal(20,8)`. Tipe: `SPOT`, `TAX` (KMK/pajak), `AVERAGE`. Kurs **dikunci saat posting, immutable**. | Auditability. |
| D4 | Sumber kurs v1: **input manual + impor CSV**. Otomatis (BI/KMK) menyusul setelah format sumber diverifikasi. | Jangan membangun di atas asumsi API yang belum dicek. |
| D5 | Persediaan = item non-moneter: dinilai IDR pada kurs tanggal transaksi (MAC tetap IDR). Mata uang asal hanya disimpan sebagai referensi. | Sesuai praktik standar; kode MAC existing tidak perlu diubah. |
| D6 | **COA baru (manual approval, sesuai aturan COA):** (a) Laba/Rugi Selisih Kurs Terealisasi, (b) Laba/Rugi Selisih Kurs Belum Terealisasi, (c) opsional Selisih Pembulatan Kurs (atau reuse `COA_PEMBULATAN` 910008). Config key `fx_realized`, `fx_unrealized`, `fx_rounding` — `env()` **tanpa default** (fail-closed, seperti `penjualan`). | Cline tidak boleh memilih akun sendiri. |
| D7 | Feature flag `platform.multicurrency_enabled` (default `false`). Flag off → perilaku identik dengan sekarang. | Pola sama dengan `grn_enabled`. |
| D8 | `accounts.currency_code` nullable (NULL = IDR). Akun kas/bank/piutang/utang valas diisi manual oleh finance. | Dasar revaluasi & pembatasan posting. |
| D9 | Pembulatan `ROUND_HALF_UP`; presisi per mata uang (`decimal_places`); `base = round(txn × rate, 2)`; toleransi 0,01 per baris, sisanya wajib baris pembulatan. | Deterministik. |
| D10 | Void transaksi valas = **jurnal reversal**, bukan hapus. | Menjawab T2. |

**Contoh angka (untuk uji penerimaan):** Invoice USD 1.000,00 @ 15.800 = Rp15.800.000. Dilunasi @ 15.900 = Rp15.900.000.
Jurnal pelunasan: `Dr Kas USD 15.900.000 (txn 1.000)` / `Cr Piutang 15.800.000 (txn 1.000)` / `Cr Laba Selisih Kurs Terealisasi 100.000 (txn NULL)`.
Revaluasi akhir bulan (saldo USD 1.000, kurs akhir 15.850): `Dr Piutang 50.000 / Cr Laba Selisih Kurs Belum Terealisasi 50.000`, lalu reversal otomatis tanggal 1 bulan berikutnya.

---

## 3. Arsitektur target

```
App\Modules\Currency\
  Models\        Currency, ExchangeRate, Settlement
  Services\      ExchangeRateService, FxPostingService, SettlementService, RevaluationService
  Support\       Money (bcmath), FxJournalGuard
  Http\Controllers\  CurrencyController, ExchangeRateController
routes/currency.php            (ikuti pola routes/customs.php)
lang/{id,en,zh_CN}/currency.php
resources/views/currency/*
Tabel (prefix cur_): cur_currencies, cur_exchange_rates, cur_settlements
Kolom aditif: journal_headers/details, accounts, sales_*, purchase_*, payment_requests
```

Invarian yang wajib dijaga di `PostingService` (parameter opsional baru, **tanpa breaking change**):
1. Debet = Kredit dalam IDR (validator existing, tidak diubah).
2. Baris ber-`amount_txn`: Debet txn = Kredit txn per mata uang.
3. Tiap baris valas: `|amount − round(amount_txn × rate, 2)| ≤ 0,01`, kecuali baris bertanda selisih kurs.
4. Flag off + parameter FX diberikan → `RuntimeException` (fail-closed).

---

## 4. Rencana bertahap (setiap fase = 1 sesi Cline = 1 laporan = 1 review)

Ukuran: S ≈ ≤½ hari, M ≈ 1–2 hari, L ≈ 3+ hari (perkiraan kasar).

| Fase | Isi | Ukuran | Gate sebelum lanjut |
|------|-----|--------|---------------------|
| 0 | Baseline & snapshot regresi (read-only) | S | Suite hijau; snapshot tersimpan |
| 1 | Master mata uang & kurs (modul baru, belum menyentuh jurnal) | M | Tes unit/feature hijau |
| 2 | Skema jurnal aditif + `PostingService` FX + `check-balance` FX | M | **Golden test**: posting IDR identik dengan sebelumnya; **approval COA D6** |
| 3 | Jurnal manual & saldo awal valas | S–M | UAT finance |
| 4 | Penjualan: SO / Invoice / Retur | L | Golden + tes USD |
| 5 | Pembelian: PO / GRN / Bill / Retur | L | Golden + tes USD |
| 6 | Pembayaran + alokasi (partial) + selisih kurs terealisasi | L | UAT finance (angka contoh §2) |
| 7 | Revaluasi akhir periode + reversal otomatis | M | Dry-run dicocokkan manual |
| 8 | Laporan & format uang (ganti "Rp" bertahap) | L | Tes penjaga grep |
| 9 | Customs/Manufacturing (opsional) | M | Keputusan bisnis |
| 10 | Hardening, dokumentasi, rollout staging → produksi | M | Checklist §6 |

Urutan **tidak boleh dilompati**: Fase 6 bergantung pada 4/5; Fase 7 bergantung pada 6.

---

## 5. Prompt Cline

> Riwayat repo ini menunjukkan agen cenderung **melebar dari scope** dan klaim "lolos" tanpa tes di lapisan HTTP. Prompt di bawah sengaja membatasi file, melarang git, dan mewajibkan output perintah asli.
> Tempel **PROMPT MASTER** di awal setiap sesi, lalu tempel prompt fase yang dikerjakan. Satu fase per sesi.

### 5.0 PROMPT MASTER (tempel di awal setiap sesi)

~~~
PERAN: Engineer Laravel 12 / PHP 8.2 pada repo app-akuntansi (ERP akuntansi double-entry).
BAHASA: Semua laporan & komentar kode dalam Bahasa Indonesia. Awali setiap baris/blok yang
kamu ubah dengan komentar `// FIX: multicurrency — <alasan singkat>`.

ATURAN KERAS (melanggar = sesi dianggap gagal):
1. Kerjakan HANYA fase yang saya tempel. Berhenti setelah laporan akhir. Jangan mulai fase berikutnya.
2. Hanya boleh membuat/mengubah file yang tercantum di bagian "BOLEH". Selain itu DILARANG,
   termasuk "perbaikan kecil" atau refactor. Temuan lain cukup dicatat di laporan.
3. DILARANG: git commit/push/checkout/reset/stash, menghapus file, mengubah .env, mengubah
   composer.json/composer.lock, membuat file sementara di root proyek.
4. COA: DILARANG mengubah nilai default config/coa.php, seeder akun, atau data tabel accounts.
   Jika perlu akun baru → tulis "MENUNGGU APPROVAL: <kebutuhan>" di laporan dan pakai key config
   `env()` tanpa default.
5. Uang: hanya bcmath/string. DILARANG float, round(), number_format() untuk perhitungan.
   number_format hanya untuk tampilan.
6. Semua operasi yang mengubah data: DB::transaction() + lockForUpdate() + guard status,
   seperti pola service yang sudah ada.
7. Migrasi: aditif saja (kolom nullable atau berdefault). DILARANG drop/rename kolom.
   Method down() harus aman dijalankan.
8. Jangan ubah signature publik yang ada secara breaking. Tambah parameter OPSIONAL di akhir.
9. Jangan menambah route/controller/job/listener yang tidak diminta.
10. Pola modul: App\Modules\Currency\*, tabel berprefix cur_, routes/currency.php,
    lang untuk 3 locale (id, en, zh_CN). Ikuti struktur modul Customs/Manufacturing yang ada.
11. Setiap perubahan perilaku WAJIB ada tes. Tes harus menguji lapisan Service DAN lapisan
    HTTP/Controller jika fitur punya endpoint. Tes yang hanya memanggil service tidak cukup.
12. Pendekatan: audit dulu → ubah sekecil mungkin (diff terarah, bukan tulis ulang file) →
    jalankan tes → baru lanjut ke perubahan berikutnya.

VERIFIKASI WAJIB (tempel output ASLI, jangan diringkas/dikarang):
- `php -l` pada setiap file PHP yang diubah/dibuat
- `php artisan test` (seluruh suite) SEBELUM mulai dan SESUDAH selesai
- `vendor/bin/phpstan analyse <file yang diubah>` bila phpstan.neon tersedia
Jika sebuah perintah tidak bisa dijalankan, tulis "TIDAK DIJALANKAN: <alasan>". Jangan klaim lolos.

FORMAT LAPORAN AKHIR:
A. Daftar file dibuat/diubah (path + ringkasan diff 1 baris)
B. Output perintah verifikasi (apa adanya)
C. Deviasi dari instruksi (jika ada) + alasannya
D. Yang BELUM dikerjakan / temuan di luar scope
E. Item "MENUNGGU APPROVAL"
~~~

### 5.1 FASE 0 — Baseline (read-only)

~~~
FASE 0 — BASELINE REGRESI (read-only, tidak mengubah perilaku aplikasi)

TUJUAN: Merekam kondisi sebelum multicurrency agar regresi bisa dideteksi.

BOLEH membuat: app/Console/Commands/FxBaselineSnapshot.php (signature: fx:baseline {--out=})
DILARANG mengubah file lain.

LANGKAH:
1. Jalankan `php artisan test`. Catat jumlah lulus/gagal (jangan perbaiki yang gagal; cukup catat).
2. Baca app/Console/Commands/CheckJournalBalance.php dan CheckAccountingIntegrity.php; catat
   `$signature` keduanya di laporan.
3. Buat command fx:baseline yang HANYA membaca (SELECT) dan menulis JSON ke
   storage/app/fx_baseline_<timestamp>.json berisi:
   - jumlah baris journal_headers dan journal_details
   - SUM(amount) per account_code per position (journal_details)
   - SUM(amount) total per position
   - jumlah purchase_orders, sales_orders, sales_invoices, purchase_bills
   - SUM(grand_total) masing-masing tabel dokumen di atas
   Command harus menolak berjalan jika OperationalDatabaseSafety melarang (pelajari kelasnya).
4. Tulis 1 tes feature untuk command ini (database sqlite in-memory seperti tes lain).
5. Jalankan command pada DB lokal dev, laporkan path file keluaran dan ringkasan angkanya.

KRITERIA SELESAI: command berjalan, tes hijau, suite tidak memburuk dibanding langkah 1.
~~~

### 5.2 FASE 1 — Master mata uang & kurs

~~~
FASE 1 — MASTER MATA UANG & KURS (belum menyentuh jurnal/dokumen)

BOLEH membuat:
- database/migrations/2026_10_10_100001_create_cur_currencies_table.php
- database/migrations/2026_10_10_100002_create_cur_exchange_rates_table.php
- app/Modules/Currency/Models/{Currency,ExchangeRate}.php
- app/Modules/Currency/Support/Money.php
- app/Modules/Currency/Services/ExchangeRateService.php
- app/Modules/Currency/Http/Controllers/{CurrencyController,ExchangeRateController}.php
- routes/currency.php  (+ satu baris pendaftaran di tempat routes/customs.php didaftarkan — cari polanya)
- resources/views/currency/{index,rates}.blade.php
- lang/{id,en,zh_CN}/currency.php
- database/seeders/CurrencySeeder.php (IDR,USD,EUR,CNY,SGD,JPY) — TIDAK dipanggil dari DatabaseSeeder
- config/platform.php: tambah SATU key 'multicurrency_enabled' => env('PLATFORM_MULTICURRENCY_ENABLED', false)
- tests/Unit/Currency/MoneyTest.php, tests/Feature/Currency/ExchangeRateTest.php

SPESIFIKASI:
- cur_currencies: code char(3) PK-unik, name, symbol, decimal_places tinyint (IDR=2, JPY=0), active.
- cur_exchange_rates: id, company_id nullable (FK companies), currency_code char(3),
  rate decimal(20,8) (1 unit valas = X mata uang dasar), rate_date date,
  type enum('SPOT','TAX','AVERAGE') default 'SPOT', source varchar(50) default 'MANUAL',
  created_by nullable FK users, timestamps.
  UNIQUE(company_id, currency_code, rate_date, type). Index (currency_code, rate_date).
- Money (static, bcmath, string in/out): add, sub, mul, div, round(value, scale) HALF_UP,
  convert(txn, rate, scale=2), compare. Tidak boleh memakai float.
- ExchangeRateService::rateFor(string $currency, Carbon $date, string $type='SPOT', ?int $companyId=null): string
  → kurs pada tanggal tsb; bila tidak ada, ambil kurs terakhir SEBELUM tanggal dalam batas
  config('platform.fx_rate_max_stale_days', 5) hari; bila tidak ada → throw ExchangeRateNotFound
  (buat exception di namespace yang sama). Mata uang dasar → selalu '1.00000000'.
- Controller: index/store/update/destroy kurs + impor CSV (kolom: currency_code,rate_date,rate,type).
  Validasi: rate > 0, maks 8 desimal, tanggal valid, mata uang aktif. Rate yang sudah dipakai
  jurnal (nanti, Fase 2+) tidak boleh dihapus — cukup beri TODO komentar, jangan implementasi.
- Middleware & otorisasi: salin persis pola routes/customs.php. Jika tidak ada pola RBAC, laporkan
  "tidak ada pola RBAC" (jangan mengarang).
- Seluruh teks UI lewat __() dan tersedia di 3 locale.

TES WAJIB:
- MoneyTest: HALF_UP (mis. 2.675 → 2.68 pada scale 2), JPY scale 0, konversi 1000.00 × 15800.00000000 = 15800000.00,
  tanpa float (uji nilai yang bermasalah di float, mis. 0.1+0.2).
- ExchangeRateTest (HTTP): tambah, duplikat ditolak, impor CSV valid & invalid, fallback stale,
  tidak ada kurs → ExchangeRateNotFound, akses tanpa login ditolak.

KRITERIA SELESAI: semua tes baru hijau, suite lama tidak memburuk, flag default false.
~~~

### 5.3 FASE 2 — Skema jurnal aditif + PostingService FX

~~~
FASE 2 — SKEMA JURNAL ADITIF + POSTING FX

PRASYARAT: Fase 1 selesai & di-review. Approval D6 (akun selisih kurs) BELUM diperlukan di fase ini;
cukup siapkan key config tanpa default.

BOLEH membuat/mengubah:
- database/migrations/2026_10_11_100001_add_currency_to_journal_tables.php
- database/migrations/2026_10_11_100002_add_currency_to_accounts_table.php
- app/Models/JournalHeader.php   (hanya $fillable)
- app/Models/JournalDetail.php   (hanya $fillable)
- app/Models/Account.php         (hanya $fillable)
- app/Support/PostingService.php (hanya tambah parameter opsional + validasi FX)
- app/Modules/Currency/Services/FxPostingService.php
- app/Console/Commands/CheckJournalBalance.php (tambah opsi --fx, perilaku default TIDAK berubah)
- config/coa.php: tambah key fx_realized, fx_unrealized, fx_rounding dengan env() TANPA default
- tests/Feature/Currency/PostingFxTest.php, tests/Feature/Currency/PostingBackwardCompatTest.php

SPESIFIKASI MIGRASI (semua nullable/berdefault — ±27 file memanggil JournalDetail::insert() dengan
array kolom lama dan HARUS tetap jalan):
- journal_headers: currency_code char(3) default 'IDR', exchange_rate decimal(20,8) default 1
- journal_details: currency_code char(3) default 'IDR', amount_txn decimal(20,4) NULL,
  exchange_rate decimal(20,8) default 1; index(currency_code)
  (NULL amount_txn artinya "sama dengan amount" — TIDAK ADA backfill data lama)
- accounts: currency_code char(3) NULL (NULL = mata uang dasar). TIDAK mengisi data apa pun.
- JANGAN menyentuh kolom debit/credit legacy (float).

SPESIFIKASI PostingService::post(array $header, array $lines, string|Closure|null $msg = null, ?array $fx = null):
- $fx = ['currency'=>'USD','rate'=>'15800.00000000'] (opsional). Bila null → perilaku PERSIS seperti sekarang.
- Bila $fx diberikan:
  a. config('platform.multicurrency_enabled') false → throw RuntimeException (fail-closed).
  b. Isi header currency_code & exchange_rate.
  c. Baris yang punya 'amount_txn': isi currency_code/exchange_rate baris tsb.
  d. Validasi: Debet txn = Kredit txn per mata uang (baris ber-amount_txn saja).
  e. Validasi: per baris valas |amount − Money::convert(amount_txn, rate)| ≤ 0.01, kecuali baris
     bertanda ['fx_adjustment'=>true] (baris selisih kurs; amount_txn NULL). Hapus key 'fx_adjustment'
     dari array sebelum insert (bukan kolom DB).
  f. Validasi balance IDR tetap memakai JournalBalanceValidator existing (jangan diubah).
- Pesan error tetap Indonesia.

FxPostingService: pembungkus tipis yang mengambil kurs lewat ExchangeRateService, menghitung amount
dari amount_txn, lalu memanggil PostingService::post(... , $fx).

TES WAJIB:
- PostingBackwardCompatTest (GOLDEN): posting tanpa $fx menghasilkan baris identik dengan sebelumnya
  (kolom lama sama, currency_code='IDR', exchange_rate=1, amount_txn NULL). Juga uji JournalDetail::insert
  dengan array kolom lama tetap berhasil.
- PostingFxTest: jurnal USD balance (txn & IDR) lolos; txn tidak balance ditolak; deviasi konversi >0.01
  ditolak; flag off + $fx → exception; baris fx_adjustment lolos tanpa amount_txn.
- Tes check-balance --fx mendeteksi jurnal valas yang sengaja dirusak.

KRITERIA SELESAI: suite lama tidak memburuk; golden test hijau; fx_* di config/coa.php tanpa default.
~~~

### 5.4 FASE 3 — Jurnal manual & saldo awal valas

~~~
FASE 3 — JURNAL MANUAL VALAS

PRASYARAT: Fase 2 selesai.

LANGKAH 1 (AUDIT, tanpa mengubah file): baca app/Http/Controllers/JournalController.php
(store/update/normalizeJournalAmounts), resources/views/journal/{create,edit}.blade.php,
app/Http/Controllers/AccountController.php (opening_balance). Tulis ringkasan alur & titik sisip di laporan.

LANGKAH 2 (UBAH, hanya jika flag aktif; flag off → UI & perilaku identik):
BOLEH mengubah: JournalController.php, journal/create.blade.php, journal/edit.blade.php,
lang/*/currency.php, tests/Feature/Currency/ManualJournalFxTest.php
- Form: pilihan mata uang (default mata uang dasar) + kolom kurs (terisi otomatis dari
  ExchangeRateService berdasarkan tanggal transaksi, dapat diubah, wajib alasan bila berbeda dari
  kurs master). Nominal per baris diisi dalam mata uang dokumen; server menghitung nilai IDR.
- Validasi `decimal:0,2` pada amount diganti sesuai decimal_places mata uang HANYA saat flag aktif.
- Posting lewat FxPostingService. Edit jurnal valas: ikuti guard yang ada (proteksi jurnal), jangan
  melonggarkan.
- Jangan ubah import CSV jurnal (JournalCsvImportService) di fase ini.

TES: HTTP create jurnal USD balance; txn tidak balance → error validasi; kurs tidak ditemukan → pesan
jelas; flag off → form & hasil sama seperti sebelumnya (golden).
~~~

### 5.5 FASE 4 — Penjualan

~~~
FASE 4 — PENJUALAN MULTICURRENCY (SO / Invoice / Retur)

PRASYARAT: Fase 2 selesai (Fase 3 disarankan).

LANGKAH 1 (AUDIT WAJIB, tanpa ubah file): baca penuh app/Services/SalesOrderService.php,
SalesOrderController.php, SalesInvoiceController.php, SalesReturnController.php, model
SalesOrder/SalesInvoice/SalesReturn(+Detail), view sales_order/*, sales_invoice/*, sales_return/*.
Laporkan: (a) semua tempat uang dihitung dengan float/(int), (b) semua tempat grand_total dijumlah.

LANGKAH 2 (UBAH):
BOLEH: migrasi baru 2026_10_12_*, model Sales*, SalesOrderService, controller & view penjualan,
lang/*/currency.php, tests/Feature/Currency/Sales*Test.php
- Migrasi aditif pada sales_orders, sales_invoices, sales_returns: currency_code char(3) default 'IDR',
  exchange_rate decimal(20,8) default 1, grand_total_txn decimal(20,4) NULL, dan *_txn lain yang
  relevan; pada tabel detail: price_txn decimal(20,4) NULL, amount_txn decimal(20,4) NULL.
  Kolom lama (sub_total, grand_total, ...) TETAP bernilai IDR (keputusan D1b).
- Di createInvoiceAndShip: tambahkan cabang valas yang aktif HANYA bila currency_code != mata uang
  dasar dan flag aktif. Cabang lama JANGAN diubah. Cabang valas memakai Money (bcmath), qty tidak
  di-cast (int), kurs diambil sekali pada tanggal invoice lalu dikunci.
- Jurnal valas: Dr Piutang (txn), Cr Penjualan (txn), Cr Pajak keluaran (txn) dst. dengan amount_txn;
  HPP/Persediaan tetap IDR murni (amount_txn NULL) — persediaan non-moneter.
- Selisih pembulatan antar baris: diserap baris terbesar dalam toleransi 0.01; di luar itu throw
  (jangan memakai akun COA yang belum di-approve).
- voidShipment pada dokumen valas: buat jurnal REVERSAL (kebalikan posisi, kurs asli), bukan hapus.
  Untuk dokumen IDR perilaku lama tidak berubah.
- Print/show invoice menampilkan mata uang dokumen + ekuivalen IDR.

TES WAJIB:
- GOLDEN: invoice IDR menghasilkan jurnal identik dengan sebelum perubahan.
- Invoice USD 1.000 @ 15.800: piutang Rp15.800.000 (txn 1.000), jurnal balance IDR & txn, HTTP create→show.
- Qty pecahan tidak terpotong pada cabang valas.
- Void invoice USD → jurnal reversal, tidak ada penghapusan.
~~~

### 5.6 FASE 5 — Pembelian

~~~
FASE 5 — PEMBELIAN MULTICURRENCY (PO / GRN / Bill / Retur)

PRASYARAT: Fase 2 selesai.

LANGKAH 1 (AUDIT WAJIB): baca penuh PurchaseOrderService.php, GrnReceivingService.php,
PurchaseOrderController, PurchaseBillController, PurchaseReceiptController, PurchaseReturnController,
GrnProtection, model PurchaseOrder/PurchaseBill/PurchaseReceipt/PurchaseReturn(+Detail), config/platform.php
(grn_enabled, order_company_scope_enabled). Laporkan titik float/(int) dan perbedaan jalur receiveLegacy vs GRN_V1.

LANGKAH 2 (UBAH):
BOLEH: migrasi baru 2026_10_13_*, model Purchase*, kedua service di atas (cabang valas saja),
controller & view pembelian, lang/*/currency.php, tests/Feature/Currency/Purchase*Test.php
- Migrasi aditif seperti Fase 4: currency_code, exchange_rate, *_txn pada PO/Bill/Receipt/Return
  dan price_txn pada detail. Kolom lama tetap IDR.
- Kurs PO bersifat indikatif. Utang dagang & persediaan dibukukan pada kurs TANGGAL PENERIMAAN
  (GRN/bill), dikunci pada dokumen penerimaan.
- Jurnal valas: Dr Persediaan (IDR = round(qty × price_txn × kurs)), Cr Utang Usaha (txn). Moving Average
  Cost tetap dalam IDR; InventoryLedger.unit_cost/total_cost tetap IDR (tidak ada perubahan skema ledger).
- Pajak masukan valas memakai kurs tipe TAX bila tersedia (fallback SPOT, dicatat di laporan).
- voidReceipt untuk dokumen valas: reversal, bukan hapus (keputusan D10). Jalur IDR tidak berubah.
- Jangan menyentuh modul Manufacturing/MaterialReceipt di fase ini.

TES WAJIB: GOLDEN IDR identik; PO USD → terima parsial 2× pada kurs berbeda → persediaan & MAC benar,
jurnal balance IDR & txn; HTTP create PO→receive; void → reversal.
~~~

### 5.7 FASE 6 — Pembayaran, alokasi, selisih kurs terealisasi

~~~
FASE 6 — PEMBAYARAN & SELISIH KURS TEREALISASI

PRASYARAT: Fase 4 & 5 selesai. APPROVAL MANUAL D6 (akun fx_realized terisi di .env oleh pemilik) dan
approval akun kas/bank valas (accounts.currency_code diisi finance) — jika belum ada, STOP dan lapor.

LANGKAH 1 (AUDIT WAJIB, tanpa ubah file): baca penuh PaymentPlanService.php, PaymentPlanController.php
(dan controller turunannya bila sudah dipecah), PaymentPlan/PaymentPlanDetail, PaymentFundingAccount.php,
PaymentRequest, docs/PAYMENT_PLAN_*.md, docs/MARVEL_GAP_PURCHASE_TO_PAYMENT.md.
Laporkan: alur pelunasan bill/invoice saat ini, bagaimana status UNPAID/PAID diubah, dan kolom nominal
(decimal(15,2) di transaksi_payment_plan_detail) apakah cukup lebar.

LANGKAH 2 (UBAH):
BOLEH: migrasi cur_settlements + kolom paid_txn pada sales_invoices & purchase_bills,
app/Modules/Currency/Models/Settlement.php, app/Modules/Currency/Services/SettlementService.php,
PaymentFundingAccount.php (menjadi sadar mata uang lewat accounts.currency_code; daftar IDR_CODES
dipertahankan sebagai fallback), titik integrasi minimal di PaymentPlanService, tes.
- cur_settlements: id, document_type, document_id, payment_ref, journal_id, currency_code,
  amount_txn decimal(20,4), rate_booked, rate_paid decimal(20,8), base_booked, base_paid decimal(20,2),
  fx_diff_base decimal(20,2), created_by, timestamps; unique(document_type, document_id, payment_ref).
- SettlementService::settle(): DB::transaction + lockForUpdate pada dokumen; tolak bila melebihi sisa
  txn; dukung parsial: selisih kurs = amount_txn × (rate_paid − rate_booked) via Money (arah laba/rugi
  mengikuti piutang/utang). Jurnal lewat FxPostingService dengan baris fx_adjustment ke akun fx_realized.
  Idempoten (payment_ref unik). Update paid_txn & payment_status.
- Pembayaran dari rekening valas ke tagihan valas (mata uang sama): tidak ada selisih kurs kas,
  hanya selisih terhadap kurs pembukuan tagihan. Pembayaran lintas mata uang → DI LUAR SCOPE, tolak
  dengan pesan jelas.

TES WAJIB: angka contoh §2 (laba Rp100.000); pelunasan parsial 2 kali; kelebihan bayar ditolak; request
ganda dengan payment_ref sama tidak membuat jurnal ganda (HTTP); jalur payment IDR existing tidak berubah (golden).
~~~

### 5.8 FASE 7 — Revaluasi akhir periode

~~~
FASE 7 — REVALUASI & REVERSAL OTOMATIS

PRASYARAT: Fase 6 selesai. APPROVAL D6 untuk akun fx_unrealized.

BOLEH: app/Modules/Currency/Services/RevaluationService.php, app/Console/Commands/FxRevalue.php,
migrasi cur_revaluation_runs, tes.
- Command: `fx:revalue {period YYYY-MM} {--company=} {--commit}`. Default DRY-RUN (hanya cetak tabel).
- Untuk setiap akun dengan accounts.currency_code != NULL: saldo txn s/d akhir periode =
  Σ(amount_txn bertanda) dari journal_details (+ jurnal Fase 2+ saja); nilai target = saldo_txn × kurs SPOT
  akhir periode; penyesuaian = target − saldo IDR tercatat. Posting jurnal tanggal akhir periode ke
  akun fx_unrealized dengan journal_type='FX_REVAL'; buat jurnal reversal tanggal hari pertama periode
  berikutnya (journal_type='FX_REVAL_REV').
- Idempoten: cur_revaluation_runs unik (company, period, account); menjalankan ulang tidak menggandakan.
- Berhenti dengan pesan jelas bila kurs akhir periode tidak ada.
- Jangan menyentuh akun IDR dan jurnal sebelum Fase 2.

TES: contoh §2 (+Rp50.000 lalu reversal); dry-run tidak menulis apa pun; run ulang idempoten;
kurs hilang → gagal bersih tanpa jurnal setengah jadi (rollback).
~~~

### 5.9 FASE 8 — Laporan & format uang

~~~
FASE 8 — LAPORAN & FORMAT UANG (kerjakan bertahap, MAKSIMAL 5 view per sesi)

PRASYARAT: Fase 4–6.

LANGKAH 1 (BARU, tanpa ubah view): buat app/Support/MoneyFormat.php + helper Blade `@money($amount, $currency = null)`
dan `@moneyBase($amount)`: simbol & desimal dari tabel cur_currencies / companies.base_currency, pemisah
mengikuti locale (id/en/zh_CN). NumberParser::formatCurrency() dipertahankan tetapi memanggil MoneyFormat
(perilaku default untuk IDR TIDAK berubah: "Rp 1.000.000,00").
Tes: format IDR identik dengan keluaran lama; USD, JPY (0 desimal), locale en.

LANGKAH 2 (per sesi, 3–5 view): ganti hardcode "Rp" HANYA untuk nilai berbasis mata uang dasar menjadi
@moneyBase; nilai dokumen valas memakai @money($x, $doc->currency_code). Urutan prioritas:
 1) report/balance-sheet, report/profit-loss (tetap IDR; tambah label "dalam IDR")
 2) ledger/index (+ kolom mata uang & amount_txn untuk akun valas)
 3) reports/ar_subledger, ap_subledger (per mata uang + ekuivalen IDR)
 4) sales_invoice/*, purchase_bill/*
 5) sisanya per modul
Laporan Neraca/Laba Rugi/Arus Kas TIDAK diubah perhitungannya.

LANGKAH 3: tes penjaga (tests/Feature/NoHardcodedCurrencyTest.php) yang gagal bila string "Rp " muncul di
view di luar daftar putih yang secara bertahap menyusut. Daftar putih awal = view yang belum dikerjakan.
~~~

### 5.10 FASE 9 & 10 (ringkas)

~~~
FASE 9 (OPSIONAL, setelah keputusan bisnis) — CUSTOMS & MANUFAKTUR
- Customs: samakan presisi kurs (cst_customs_documents.exchange_rate decimal(20,4) vs form step 0.000001 →
  usulkan migrasi ke decimal(20,8) dan sesuaikan validasi), hubungkan currency dokumen ke cur_currencies
  (tanpa FK keras), isi kurs dari tipe TAX. Jangan ubah alur H2H CEISA.
- Manufaktur: audit dulu seluruh service (WorkOrder, MaterialReceipt, Maklun*, Cutting, Stitching);
  usulkan perubahan dalam laporan SEBELUM menulis kode. Bahan baku impor valas mengikuti pola Fase 5.

FASE 10 — HARDENING
- phpstan + seluruh suite + tes MySQL (phpunit.mgi-mysql.xml) pada DB uji.
- Perbarui PRD.md (hapus "tidak ada multi-currency"), tulis docs/MULTICURRENCY.md (alur, invarian, kebijakan kurs).
- Jalankan fx:baseline lagi dan bandingkan dengan Fase 0: untuk data IDR harus identik.
- Skrip verifikasi tools/verify-multicurrency.ps1 mengikuti pola tools/verify-*.ps1 yang ada.
~~~

---

## 6. Checklist regresi, rollout & rollback

**Setelah setiap fase (manusia, bukan agen):**
1. `git diff --stat` — pastikan hanya file BOLEH yang berubah; tidak ada file scratch di root.
2. `git log` — pastikan tidak ada commit/push otomatis dari alat checkpoint (riwayat repo ini pernah mengalaminya).
3. Jalankan ulang tes sendiri; bandingkan `fx:baseline` dengan Fase 0.
4. Baca diff `PostingService.php` baris demi baris (titik paling berbahaya).

**Rollout:** DB staging hasil restore produksi → `migrate` → `fx:baseline` dibandingkan → aktifkan flag hanya di staging → UAT finance dengan angka contoh §2 → produksi dengan flag **off** → aktifkan flag per perusahaan setelah rekening/akun valas siap.

**Rollback:** matikan flag (perilaku kembali identik karena semua kolom aditif & default). Migrasi `down()` hanya bila belum ada data valas.

**Keputusan yang saya butuhkan dari Anda sebelum Fase 1:** D1/D1b (amount tetap IDR), D2 (satu mata uang per dokumen), D4 (kurs manual/CSV dulu), D6 (nama & kode akun selisih kurs — Anda yang menetapkan), daftar mata uang awal, dan apakah Fase 9 masuk scope.

# Rencana Multicurrency — app-akuntansi (v2)

Basis: **branch `main`** (806 file PHP) — menggantikan v1 yang berbasis branch `feat/marvel…` (708 file).
Fase 0, 1, 2, 2b, dan sesi B sudah selesai & lulus pada salinan MariaDB (`erp_baseline_copy`).
**Gunakan v2 mulai Fase 3. Jangan pakai prompt Fase 3–10 dari v1.**

---

## 0. Status

| Fase | Status | Bukti |
|------|--------|-------|
| 0 Baseline | Selesai | `fx:baseline`, snapshot SHA-256 `5f5c3a39…8e3a` |
| 1 Master mata uang & kurs | Selesai | tes Currency hijau |
| 2 Skema jurnal aditif + `PostingService` FX | Selesai | golden test + rollback test + migrasi di MariaDB |
| 2b `first_used_at` kurs | Selesai | tes HTTP 422 |
| B Perbaikan lock, key COA, duplikat kurs, `quoted_*` | Selesai | suite 533 hijau; baseline identik |
| 3–10 | **Belum** | — |

**Tugas Anda sebelum Fase 3:**
1. Komit pekerjaan ke branch `feat/multicurrency` (Cline tidak boleh memakai Git yang mengubah repo). Jangan ikut-sertakan `README.md`, `.agents/`, `serve-lan.ps1`, `skills-lock.json` kecuali Anda memang menghendakinya.
2. Jalankan di salinan (phpMyAdmin) dan pastikan semuanya `0`:
   ```sql
   SELECT COUNT(*) FROM journal_details WHERE currency_code <> 'IDR' OR exchange_rate <> 1 OR amount_txn IS NOT NULL;
   SELECT COUNT(*) FROM journal_headers WHERE currency_code <> 'IDR' OR exchange_rate <> 1;
   ```
3. Konfirmasi pemetaan akun: **810002 = Laba Selisih Kurs Terealisasi, 910002 = Rugi Selisih Kurs Terealisasi**, lalu isi `.env`: `COA_FX_REALIZED_GAIN`, `COA_FX_REALIZED_LOSS`. Akun belum terealisasi (`COA_FX_UNREALIZED_GAIN/LOSS`) baru wajib sebelum Fase 7.
4. Rekonsiliasi ledger migrasi database operasional (`migrate:status`) sebelum rollout. Salinan menunjukkan ±40 migrasi pending, sebagian tabelnya sudah ada.

---

## 1. Temuan baru dari audit `main` (menggantikan K4 v1)

| # | Sev. | Temuan | Dampak untuk multicurrency |
|---|------|--------|----------------------------|
| M1 | Tinggi | **Alokasi pembayaran sudah ada** (`invoice_payment_allocations`, `bill_payment_allocations`) tetapi hanya IDR `decimal(20,2)` dan berlogika float: `round((float)…)`, pembanding `!==`. | Fase 6 memperluas tabel & service ini, bukan membuat `cur_settlements`. |
| M2 | Tinggi | `InvoicePaymentAllocationService::allocate` menolak jurnal bila **setiap baris** bukan "kredit piutang" atau "debet kas", dan mensyaratkan `kredit piutang === debet kas`. | Pelunasan valas dengan baris selisih kurs **pasti ditolak** sampai cabang valas ditambahkan. |
| M3 | Tinggi | `InvoiceCancellationService` membuat jurnal reversal dengan menyalin kolom satu per satu (`account_code, helper_code, position, amount`). | Kolom `currency_code/amount_txn/exchange_rate` **tidak ikut** → reversal jurnal valas membuat saldo txn piutang salah. Berlaku juga untuk jalur reversal lain (Maklun, `MaterialReceiptVoidService`, `voidReceipt`). |
| M4 | Tinggi | **Penutupan periode hanya preview untuk lingkungan testing**: `AccountingPeriodGuard::enabled()` dan `AccountingPeriodService` mensyaratkan `period_lifecycle_preview_enabled` **dan** `app()->environment('testing')`. | Di produksi periode belum bisa ditutup. Revaluasi tidak boleh bergantung pada penguncian periode; harus punya deteksi "data basi" sendiri dan tetap memanggil `AccountingPeriodGuard::dates()` agar siap saat fitur periode diaktifkan. |
| M5 | Sedang | `OpeningBalanceService` memakai **satu header per tanggal** (`JRN-SA-<tanggal>`) untuk semua akun. | Jurnal saldo awal pasti bercampur mata uang (bank USD vs modal IDR) → invarian "debet txn = kredit txn per mata uang" **tidak berlaku** untuk tipe `OPENING`. |
| M6 | Sedang | `PaymentMakerChecker::fingerprint()` mengamankan pembayaran setelah disetujui. | Jika pembayaran valas ditambahkan, `currency_code`/`rate`/`amount_txn` **wajib masuk fingerprint**, kalau tidak, pengubahan kurs setelah persetujuan tidak terdeteksi. |
| M7 | Sedang | `FxPostingService` men-hardcode basis `'IDR'` saat `companyId` kosong (T1 v1). | Pastikan sumber mata uang dasar tunggal; jangan menyebar hardcode ke Fase 4+. |
| M8 | Rendah | Unique key `cur_rate_scope_unique` tidak mencegah duplikat saat `company_id` NULL. | Sudah ditambal di aplikasi (kunci baris mata uang). Uji dua koneksi paralel di Fase 10. |

Temuan v1 yang **masih berlaku:** K1–K3, K5, T2–T5, S1–S4 (K2 makin relevan: service baru juga memakai float).

---

## 2. Keputusan desain (ringkasan; bagian bertanda ★ baru/berubah)

| ID | Keputusan |
|----|-----------|
| D1/D1b | `amount` dan kolom total dokumen **tetap IDR**; tambahan `currency_code`, `amount_txn`, `exchange_rate`, `*_txn`. (disetujui) |
| D2 | Satu mata uang per dokumen. ★ **Pengecualian:** jurnal tipe `OPENING` dan jurnal revaluasi boleh bercampur mata uang (header IDR, baris membawa snapshot masing-masing). (disetujui + pengecualian) |
| D3 ★ | Kurs: `rate` per 1 unit, `decimal(20,8)`; TAX (KMK) punya `rate_date`–`valid_until` tanpa fallback; SPOT/BI kurs tengah dengan fallback ≤ `fx_rate_max_stale_days`. `quoted_unit`/`quoted_rate` menyimpan angka asli lampiran KMK. |
| D5 | Persediaan non-moneter: dinilai IDR pada kurs transaksi. |
| D6 ★ | Empat key: `fx_realized_gain` (810002), `fx_realized_loss` (910002), `fx_unrealized_gain`, `fx_unrealized_loss`; `fx_rounding` memakai ulang akun pembulatan. `env()` tanpa default. |
| D8 ★ | `accounts.currency_code` hanya untuk akun **satu mata uang** (kas/bank valas). Akun kontrol Piutang/Hutang Usaha tetap satu akun; ditandai `is_fx_monetary` (Fase 7) dan direvaluasi per (akun, mata uang baris). |
| D10 | Void/pembatalan dokumen valas = jurnal **reversal** yang menyalin seluruh kolom FX. |
| D11 ★ | Pelunasan valas memperluas tabel alokasi yang ada (kolom aditif); alokasi dilacak dalam **txn** (sisa tagihan txn), basis IDR dihitung dari kurs pembukuan. |
| D12 ★ | Revaluasi: dry-run default, idempoten, deteksi data basi (transaksi valas bertanggal ≤ periode yang sudah direvaluasi muncul setelah run), memanggil `AccountingPeriodGuard::dates()`. |
| D13 ★ | Cabang valas hanya aktif bila `currency_code != mata uang dasar` dan flag aktif; **cabang IDR tidak boleh berubah** (golden test wajib). |

Invarian posting (`PostingService`, tetap):
1. Debet = Kredit dalam IDR.
2. Baris ber-`amount_txn`: debet txn = kredit txn per mata uang — **kecuali tipe `OPENING`/revaluasi (D2)**.
3. Tiap baris valas: `|amount − round(amount_txn × rate, 2)| ≤ 0,01`, kecuali baris `fx_adjustment`.
4. Flag mati + `$fx` ≠ null → `RuntimeException`.

---

## 3. Peta fase

| Fase | Isi | Ukuran |
|------|-----|--------|
| 3 | Jurnal manual valas + saldo awal valas (kas/bank) | M |
| 4A | Penjualan: SO + Invoice + pembatalan (reversal menyalin FX) | L |
| 4B | Retur penjualan | M |
| 5A | Pembelian: PO + GRN + Bill + void/reversal | L |
| 5B | Retur pembelian | M |
| 6A | Pelunasan invoice valas (alokasi) + selisih kurs terealisasi | L |
| 6B | Pembayaran bill valas via Payment Plan (maker-checker) | L |
| 7 | Revaluasi akhir periode + reversal otomatis | M |
| 8 | Laporan & format uang | L |
| 9 | Customs / Manufaktur (opsional) | M |
| 10 | Hardening, konkurensi, rollout | M |

Satu fase = satu sesi Cline = satu laporan = satu review. Urutan tidak boleh dilompati (6 butuh 4/5; 7 butuh 6).

---

## 4. Prompt

### 4.0 PROMPT MASTER (tempel di awal setiap sesi)

~~~
PERAN: Engineer Laravel 12 / PHP 8.2 pada repo app-akuntansi (ERP akuntansi double-entry).
BAHASA: Laporan & komentar kode dalam Bahasa Indonesia. Awali baris/blok yang kamu ubah dengan
`// FIX: multicurrency — <alasan singkat>`.

ATURAN KERAS (melanggar = sesi gagal):
1. Kerjakan HANYA fase yang saya tempel. Berhenti setelah laporan akhir.
2. Hanya boleh membuat/mengubah file di bagian "BOLEH". Temuan lain cukup dicatat di laporan.
3. GIT: perintah yang MENGUBAH repo dilarang (commit, push, checkout, reset, stash, clean, rebase, merge,
   branch). Perintah baca (status, diff, log, show) BOLEH dan diwajibkan untuk bukti.
4. DILARANG: menghapus file, mengubah .env, composer.json/lock, AppServiceProvider,
   OperationalDatabaseSafety, atau membuat file sementara di root proyek.
5. COA: DILARANG mengubah nilai default config/coa.php, seeder akun, atau data tabel accounts.
   Butuh akun baru → tulis "MENUNGGU APPROVAL: <kebutuhan>".
6. Uang: hanya bcmath/string (kelas Money). DILARANG float, round(), (int) pada qty uang untuk
   perhitungan di cabang valas. number_format hanya untuk tampilan.
7. Operasi ubah data: DB::transaction() + lockForUpdate() + guard status; panggil
   AccountingPeriodGuard::dates([...]) untuk tanggal transaksi pada jalur valas baru.
8. Migrasi aditif (nullable/berdefault). DILARANG drop/rename. down() menolak bila ada data valas yang hilang.
9. Jangan ubah signature publik secara breaking; tambah parameter OPSIONAL di akhir. Cabang IDR
   TIDAK BOLEH berubah perilaku (golden test wajib).
10. Jangan menambah route/controller/job/listener yang tidak diminta.
11. Pola modul: App\Modules\Currency\*, tabel berprefix cur_, lang 3 locale (id, en, zh_CN).
12. Setiap perubahan perilaku WAJIB tes di lapisan Service DAN HTTP bila ada endpoint.
13. Pendekatan: AUDIT dulu (baca file, laporkan temuan) → ubah sekecil mungkin (diff terarah) →
    tes terarah → langkah berikutnya.

VERIFIKASI WAJIB (tempel output ASLI, jangan diringkas/dikarang):
- php -l pada setiap file yang diubah/dibuat
- php artisan test (seluruh suite) SEBELUM dan SESUDAH; gunakan --compact bila ringkasan tidak muncul
- vendor/bin/phpstan analyse pada file yang diubah; tandai temuan yang berasal dari baris yang kamu ubah
- git status --short dan git diff --stat di akhir; hanya file BOLEH yang boleh muncul
- Output panjang: tulis ke storage/logs/<nama>.log, tempel path + 40 baris terakhir. JANGAN merekonstruksi
  output yang terpotong; tulis "TERPOTONG" dan jalankan ulang bagian itu.
Jika perintah tidak bisa dijalankan: "TIDAK DIJALANKAN: <alasan>". Jangan klaim lolos.

DATABASE: pengujian MariaDB HANYA pada salinan erp_baseline_copy dengan proses yang env-nya
DB_DATABASE=erp_baseline_copy (jangan ubah .env). Pra-syarat setiap sesi: hash .env sebelum/sesudah, tidak
ada bootstrap/cache/config.php, DB_URL tidak diset, nama DB aktual (SELECT DATABASE()) tercetak,
guard = ALLOWED. Dilarang menyentuh database berawalan mgi_fresh_.

FORMAT LAPORAN AKHIR:
A. File dibuat/diubah (path + ringkasan diff)
B. Output verifikasi (apa adanya)
C. Deviasi dari instruksi + alasan
D. Belum dikerjakan / temuan di luar scope
E. MENUNGGU APPROVAL
~~~

### 4.1 FASE 3 — Jurnal manual valas & saldo awal kas/bank valas

~~~
FASE 3 — JURNAL MANUAL VALAS + SALDO AWAL KAS/BANK VALAS
PRASYARAT: sesi B lulus, flag platform.multicurrency_enabled hanya aktif di lingkungan tes.

LANGKAH 1 (AUDIT, tanpa ubah file) — laporkan ringkasan alur & titik sisip:
 - JournalController (store/update/destroy/normalize amount), view journal/create & edit
 - AccountController (opening_balance), view account/opening_balance, OpeningBalanceService
 - PostingService + FxPostingService versi sekarang (validateLines)
 - nilai yang dipakai kolom journal_type (cari enum/validasi) dan pemakai journal_type='MANUAL'
   (InvoicePaymentAllocationService mensyaratkannya)
 - JournalCsvImportService/JournalImport: HANYA dicatat, tidak diubah.

LANGKAH 2 (UBAH) — BOLEH: JournalController, view journal/create+edit, FxPostingService, PostingService
 (hanya blok FX), AccountController opening_balance + view-nya, OpeningBalanceService (hanya bila perlu),
 lang/*/currency.php, tests/Feature/Currency/*
 a. Jurnal manual 1 mata uang asing: pilih mata uang + kurs (otomatis dari ExchangeRateService berdasar
    tanggal; dapat diubah, ALASAN WAJIB bila berbeda dari master). Nominal baris dalam mata uang dokumen;
    server menghitung IDR via Money. Posting lewat FxPostingService. Flag mati → form & hasil IDENTIK.
    Panggil AccountingPeriodGuard::dates([tanggal]) di dalam transaksi.
    Kurs hasil override TIDAK menandai first_used_at master (hanya kurs dari master yang menandai).
 b. Edit/hapus jurnal valas mengikuti guard yang ada (ProtectedJournalQuery); jangan melonggarkan.
 c. Mode OPENING bercampur mata uang: PostingService menerima $fx['mixed']=true HANYA bila
    journal_type === 'OPENING'. Tiap baris wajib membawa currency & rate sendiri; header tetap IDR.
    Invarian: balance IDR dan toleransi konversi per baris; invarian "txn balance per mata uang" DILEWATI
    untuk OPENING. Tipe lain dengan mixed=true → exception.
 d. Saldo awal akun yang accounts.currency_code terisi: form menerima amount_txn + kurs (default: SPOT
    pada tanggal cut-off) lalu menghitung IDR. Akun Piutang/Hutang Usaha valas TIDAK didukung lewat saldo
    awal — tampilkan pesan "masukkan sebagai dokumen terbuka (invoice/bill)". Jangan mengubah data accounts.
 e. Satu header OPENING per tanggal tetap (OpeningBalanceService); jangan ubah kunci JRN-SA-<tanggal>.

TES: jurnal USD balance (IDR & txn) HTTP create→show; txn tidak balance → error validasi; kurs tidak ada →
pesan jelas; override kurs tanpa alasan ditolak; periode tutup (aktifkan di tes) ditolak; OPENING campuran
USD+IDR lolos; mixed pada tipe non-OPENING ditolak; flag mati → golden; opening IDR lama tetap sama.
~~~

### 4.2 FASE 4A — Penjualan (SO / Invoice / Pembatalan)

~~~
FASE 4A — PENJUALAN MULTICURRENCY (SO, Invoice, Pembatalan Invoice)
PRASYARAT: Fase 3 selesai.

LANGKAH 1 (AUDIT WAJIB, tanpa ubah file): baca penuh SalesOrderService, SalesOrderController,
SalesInvoiceController, InvoiceCancellationService, model SalesOrder/SalesInvoice(+Detail), view
sales_order/*, sales_invoice/*. Laporkan: (a) semua float/(int) pada hitungan uang, (b) semua tempat
grand_total dijumlah, (c) semua tempat yang menyalin baris jurnal (reversal) — daftar lengkap.

LANGKAH 2 (UBAH) — BOLEH: migrasi baru 2026_10_20_*, model Sales*, SalesOrderService,
InvoiceCancellationService, controller/view penjualan, lang/*/currency.php, tests/Feature/Currency/Sales*
 - Migrasi aditif sales_orders, sales_invoices (+ detail): currency_code char(3) default 'IDR',
   exchange_rate decimal(20,8) default 1, grand_total_txn decimal(20,4) NULL, price_txn/amount_txn NULL.
   Kolom lama TETAP IDR.
 - createInvoiceAndShip: cabang valas bila currency_code != mata uang dasar & flag aktif; cabang IDR
   JANGAN diubah. Cabang valas: bcmath, qty tidak di-cast (int), kurs diambil sekali pada tanggal invoice
   lalu dikunci; kurs dari master → tandai first_used_at lewat FxPostingService.
 - Jurnal valas: Dr Piutang (txn), Cr Penjualan (txn), Cr Pajak keluaran (txn). HPP/Persediaan IDR murni
   (amount_txn NULL). Selisih pembulatan antar baris diserap baris terbesar dalam toleransi 0.01; lebih dari
   itu → exception.
 - InvoiceCancellationService: reversal HARUS menyalin header currency_code/exchange_rate dan baris
   currency_code/amount_txn/exchange_rate (amount_txn tetap positif, posisi dibalik). Invoice IDR:
   perilaku & hasil identik (golden).
 - Show/print invoice: mata uang dokumen + ekuivalen IDR.
TES: GOLDEN invoice IDR & pembatalan IDR identik; invoice USD 1.000 @15.800 → piutang Rp15.800.000
(txn 1.000), balance IDR & txn; qty pecahan tidak terpotong; pembatalan USD → saldo txn piutang kembali 0
dan saldo IDR kembali 0; HTTP create→show→cancel; periode tutup menolak.
~~~

### 4.3 FASE 4B — Retur penjualan

~~~
FASE 4B — RETUR PENJUALAN VALAS
LANGKAH 1 (AUDIT): SalesReturnController, SalesReturnQuotaService, model SalesReturn(+Detail),
view sales_return/*, hubungan retur↔invoice_payment_allocations (kolom total_refund_amount).
LANGKAH 2 (UBAH): retur atas invoice valas memakai KURS PEMBUKUAN INVOICE (bukan kurs hari ini) untuk
membalik penjualan & piutang; refund tunai valas di luar scope (tolak dengan pesan jelas). Retur invoice IDR
tidak berubah (golden). Salin kolom FX pada jurnal retur. Tes HTTP + golden.
~~~

### 4.4 FASE 5A — Pembelian (PO / GRN / Bill / Void)

~~~
FASE 5A — PEMBELIAN MULTICURRENCY (PO, GRN, Bill, Void)
PRASYARAT: Fase 3 selesai (4A disarankan lebih dulu).

LANGKAH 1 (AUDIT WAJIB): baca penuh PurchaseOrderService (receiveLegacy, voidReceipt), GrnReceivingService,
PurchaseOrderController, PurchaseBillController, PurchaseReceiptController, GrnProtection,
MaterialReceiptVoidService, model Purchase*, config/platform.php (grn_enabled,
order_company_scope_enabled). Laporkan: float/(int), perbedaan jalur legacy vs GRN_V1, SEMUA tempat yang
menghapus atau menyalin baris jurnal (void), dan bagaimana ProtectedJournalQuery dipakai.

LANGKAH 2 (UBAH) — BOLEH: migrasi baru 2026_10_21_*, model Purchase*, PurchaseOrderService,
GrnReceivingService (cabang valas), controller/view pembelian, lang, tests/Feature/Currency/Purchase*
 - Kolom aditif seperti 4A pada PO/Bill/Receipt (+detail price_txn). Kolom lama tetap IDR.
 - Kurs PO indikatif. Persediaan & utang dibukukan pada kurs TANGGAL PENERIMAAN (GRN/bill), dikunci pada
   dokumen penerimaan.
 - Jurnal valas: Dr Persediaan (IDR = round(qty × price_txn × kurs)), Cr Utang Usaha (txn). MAC & ledger
   tetap IDR (tanpa perubahan skema InventoryLedger).
 - Pajak masukan valas memakai kurs TAX bila tersedia, fallback SPOT (catat di laporan).
 - voidReceipt dokumen valas: REVERSAL yang menyalin kolom FX (D10), bukan hapus. Jalur IDR tidak berubah.
 - Jangan menyentuh MaterialReceipt Manufacturing di fase ini.
TES: GOLDEN IDR identik; PO USD → terima parsial 2× pada kurs berbeda → persediaan & MAC benar, balance IDR
& txn; HTTP create PO→receive; void → reversal (tidak ada penghapusan); periode tutup menolak.
~~~

### 4.5 FASE 5B — Retur pembelian

~~~
FASE 5B — RETUR PEMBELIAN VALAS
LANGKAH 1 (AUDIT): PurchaseReturnController, PurchaseReturnValuationService, model PurchaseReturn(+Detail).
LANGKAH 2 (UBAH): retur valas memakai kurs pembukuan bill/GRN asal untuk membalik utang & persediaan;
selisih valuasi MAC tetap IDR. Retur IDR tidak berubah (golden). Salin kolom FX pada jurnal retur. Tes HTTP.
~~~

### 4.6 FASE 6A — Pelunasan invoice valas

~~~
FASE 6A — PELUNASAN INVOICE VALAS + SELISIH KURS TEREALISASI
PRASYARAT: Fase 4A selesai. APPROVAL: COA_FX_REALIZED_GAIN/LOSS terisi di .env oleh pemilik; akun kas/bank
valas dibuat finance dan accounts.currency_code diisi. Jika belum → BERHENTI dan lapor.

LANGKAH 1 (AUDIT WAJIB, tanpa ubah file): baca InvoicePaymentAllocationService (allocate & reverse) penuh,
InvoicePaymentAllocationController, AuditPaymentAllocations (command), tes PaymentAllocation*Test,
AdvancedReportController (pemakai tabel alokasi), PaymentFundingAccount. Laporkan: syarat yang membuat
jurnal valas ditolak (kami tahu: setiap baris harus AR-kredit/kas-debet, AR === kas, journal_type MANUAL).

LANGKAH 2 (UBAH) — BOLEH: migrasi baru 2026_10_22_* (aditif pada invoice_payment_allocations:
currency_code char(3) default 'IDR', amount_txn decimal(20,4) NULL, rate_paid decimal(20,8) default 1,
fx_diff_base decimal(20,2) default 0; dan sales_invoices.paid_txn decimal(20,4) default 0),
InvoicePaymentAllocationService (cabang valas), app/Modules/Currency/Services/SettlementService.php,
InvoicePaymentAllocationController + view alokasi (tampilan selisih kurs), PaymentFundingAccount.php
(sadar mata uang via accounts.currency_code; IDR_CODES tetap sebagai fallback), AuditPaymentAllocations,
lang, tests
 - Cabang valas aktif bila invoice.currency_code != mata uang dasar. Cabang IDR TIDAK berubah (golden).
 - Alur: user memposting jurnal MANUAL (Dr Kas valas txn, Cr Piutang txn, baris selisih kurs IDR-only ke
   akun fx_realized_gain/loss), lalu alokasi memvalidasi:
   * baris diizinkan: AR-kredit, kas-debet, baris selisih kurs (akun fx_realized_gain/loss saja);
   * txn: Σ AR-kredit amount_txn === Σ kas-debet amount_txn, mata uang = mata uang invoice;
   * basis AR yang dilunasi = round(amount_txn × invoice.exchange_rate); pelunasan TERAKHIR (sisa txn → 0)
     melunasi sisa basis IDR persis (menyerap pembulatan);
   * selisih = kas IDR − basis AR dilunasi; baris selisih kurs HARUS sama (arah laba/rugi sesuai tanda);
   * jumlah txn ≤ sisa txn (grand_total_txn − paid_txn − retur); bandingkan dengan bcmath, bukan float.
 - SettlementService (fungsi murni, mudah dites): previewFx(invoice, amount_txn, rate_paid) → baris jurnal
   yang disarankan + selisih. Dipakai view alokasi sebagai pratinjau (tanpa memposting).
 - Update paid_txn & payment_status (PARTIAL/PAID) berdasarkan txn. Reverse alokasi valas membalik paid_txn.
 - AuditPaymentAllocations: tambah pemeriksaan konsistensi txn & selisih kurs.
 - Pembayaran lintas mata uang (kas IDR untuk invoice USD) → TOLAK dengan pesan jelas.
TES: angka contoh (invoice 1.000 USD @15.800; bayar @15.900 → laba Rp100.000); parsial 2×; pelunasan terakhir
menyerap pembulatan; kelebihan txn ditolak; alokasi ganda ditolak (HTTP); reverse mengembalikan sisa;
rugi kurs (bayar @15.700); golden alokasi IDR.
~~~

### 4.6b FASE 6B — Pembayaran bill valas (Payment Plan)

~~~
FASE 6B — PEMBAYARAN BILL VALAS VIA PAYMENT PLAN (maker-checker)
PRASYARAT: Fase 5A & 6A selesai.

LANGKAH 1 (AUDIT WAJIB, tanpa ubah file): baca penuh PaymentPlanService, PaymentPlanController
(sekitar baris 636: pemanggil BillPaymentAllocationService), PaymentMakerChecker (approve/edited/verified/
fingerprint), PaymentPlan/PaymentPlanDetail, BillPaymentAllocationService, AuthorizePaymentAction,
docs/PAYMENT_PLAN_*.md. Laporkan: bagaimana nominal_aktual_efektif dihitung, di mana jurnal pembayaran
dibuat, dan tepatnya bidang apa saja yang masuk fingerprint.

LANGKAH 2 (UBAH) — BOLEH: migrasi baru 2026_10_23_* (aditif: payment plan header/detail currency_code,
exchange_rate, nominal_txn; bill_payment_allocations: currency_code, amount_txn, rate_paid, fx_diff_base;
purchase_bills.paid_txn), PaymentPlanService/Controller (cabang valas minimal), PaymentMakerChecker
(HANYA fingerprint: tambahkan currency_code, exchange_rate, nominal_txn), BillPaymentAllocationService
(cabang valas), lang, tests
 - Cabang IDR identik (golden). Fingerprint pembayaran IDR lama TIDAK BOLEH berubah nilainya
   (tambahkan bidang FX hanya bila currency_code != mata uang dasar), agar pembayaran yang sudah disetujui
   tetap valid.
 - Pembayaran bill valas: rekening sumber harus bernomor mata uang yang sama; selisih kurs terealisasi
   dihitung seperti 6A (arah kebalikan: utang → bayar lebih murah = laba).
 - Mengubah currency/kurs/nominal_txn setelah persetujuan → verifikasi fingerprint GAGAL (tes wajib).
TES: bill 500 USD @15.800 dibayar @15.700 → laba Rp50.000; parsial; maker-checker menolak edit kurs;
golden payment IDR & fingerprint IDR lama tidak berubah; HTTP end-to-end.
~~~

### 4.7 FASE 7 — Revaluasi

~~~
FASE 7 — REVALUASI AKHIR PERIODE + REVERSAL OTOMATIS
PRASYARAT: Fase 6A selesai (6B disarankan). APPROVAL: COA_FX_UNREALIZED_GAIN/LOSS terisi.

LANGKAH 1 (AUDIT, tanpa ubah file): AccountingPeriodGuard & AccountingPeriodService (catat: penutupan
periode hanya aktif di lingkungan testing), SourceJournalProtection, ProtectedJournalQuery, kolom
journal_type (nilai yang diizinkan), JournalController (edit/hapus jurnal AUTO).

LANGKAH 2 (UBAH) — BOLEH: migrasi baru 2026_10_24_* (accounts.is_fx_monetary boolean default false;
cur_revaluation_runs: id, company_id, period char(7), account_code, currency_code, txn_balance decimal(20,4),
base_balance decimal(20,2), rate decimal(20,8), adjustment decimal(20,2), journal_id, reversal_journal_id,
created_by, timestamps; unique(company_id, period, account_code, currency_code)),
RevaluationService, app/Console/Commands/FxRevalue.php, model, tests
 - fx:revalue {period YYYY-MM} {--company=} {--commit}. Default DRY-RUN (cetak tabel, tulis nol baris).
 - Cakupan: akun dengan accounts.currency_code ≠ NULL (satu mata uang) DAN akun is_fx_monetary = 1
   (dikelompokkan per currency_code baris). Saldo txn = Σ amount_txn bertanda s/d akhir periode (baris
   dengan amount_txn NULL diabaikan); target = saldo_txn × kurs SPOT akhir periode; penyesuaian =
   target − saldo IDR baris-baris valas tsb.
 - Jurnal tanggal akhir periode ke fx_unrealized_gain/loss (baris selisih IDR-only); jurnal reversal tanggal
   hari pertama periode berikutnya. Kedua jurnal memanggil AccountingPeriodGuard::dates() dan memakai
   journal_type yang diizinkan hasil audit (laporkan pilihan).
 - Jurnal revaluasi dilindungi dari edit/hapus manual (ikuti pola SourceJournalProtection; laporkan bila
   perlu perubahan di luar BOLEH → MENUNGGU APPROVAL).
 - Idempoten via cur_revaluation_runs. Kurs akhir periode tidak ada → gagal bersih (rollback penuh).
 - DETEKSI BASI (karena periode belum bisa ditutup): setiap run menyimpan hash agregat saldo; run ulang
   pada periode yang sama dengan agregat berbeda → tolak dengan pesan "ada transaksi valas baru sejak
   revaluasi; batalkan run sebelumnya dulu" (pembatalan = reversal penuh jurnal run itu).
 - Mulai jalankan dengan --commit hanya bila saya menyetujui hasil dry-run.
TES: angka contoh (+Rp50.000 lalu reversal); dry-run tidak menulis; run ulang idempoten; transaksi baru
sesudah run terdeteksi basi; kurs hilang → rollback; akun kontrol AR campuran USD+EUR direvaluasi per mata uang.
~~~

### 4.8 FASE 8 — Laporan & format uang (≤ 5 view per sesi)

~~~
FASE 8 — LAPORAN & FORMAT UANG
LANGKAH 1: app/Support/MoneyFormat.php + helper Blade @money($amount, $currency=null) dan @moneyBase($amount):
simbol & desimal dari cur_currencies / mata uang dasar perusahaan, pemisah mengikuti locale (id/en/zh_CN).
NumberParser::formatCurrency() tetap, memanggil MoneyFormat; keluaran IDR default TIDAK berubah ("Rp 1.000.000,00").
Tes: IDR identik dengan lama; USD; JPY/KRW 0 desimal; locale en.
LANGKAH 2 (per sesi 3–5 view; urutan): 1) report/balance-sheet & profit-loss (tetap IDR; label "dalam IDR"),
2) ledger/index (tambah kolom mata uang & amount_txn untuk akun valas), 3) reports/ar_subledger & ap_subledger
(per mata uang + ekuivalen IDR + sisa txn dari alokasi), 4) sales_invoice/*, purchase_bill/*, 5) sisanya per modul.
Perhitungan Neraca/Laba Rugi/Arus Kas TIDAK diubah.
LANGKAH 3: tests/Feature/NoHardcodedCurrencyTest.php — gagal bila "Rp " muncul di view di luar daftar putih yang
menyusut bertahap.
~~~

### 4.9 FASE 9 (opsional) & FASE 10

~~~
FASE 9 (OPSIONAL, setelah keputusan bisnis)
Customs: samakan presisi kurs (cst_customs_documents.exchange_rate decimal(20,4) vs form step 0.000001 →
usulkan decimal(20,8)), hubungkan currency ke cur_currencies (tanpa FK keras), isi dari kurs TAX; jangan ubah
alur H2H CEISA. Manufaktur: AUDIT dulu seluruh service (WorkOrder, MaterialReceipt, Maklun*, Cutting,
Stitching); usulkan perubahan dalam laporan SEBELUM menulis kode.

FASE 10 — HARDENING & ROLLOUT
- phpstan + suite + tes MariaDB pada salinan; ukur angka PHPStan sebelum/sesudah dengan git worktree dari
  commit awal (tanpa mengubah working tree).
- UJI KONKURENSI NYATA: dua koneksi MariaDB paralel untuk (a) simpan kurs duplikat/tumpang tindih,
  (b) dua alokasi pelunasan pada invoice yang sama, (c) posting valas bersamaan dengan simpan kurs.
- Uji skala: bangkitkan data sintetis (≥ 500 ribu baris journal_details) di salinan; ukur ADD COLUMN/INDEX
  dan waktu fx:revalue.
- Rekonsiliasi ledger migrasi database operasional; rollout: restore dump terbaru → migrate → fx:baseline
  dibanding → UAT finance (angka contoh) → produksi dengan flag OFF → aktifkan per perusahaan.
- Perbarui PRD.md; tulis docs/MULTICURRENCY.md (alur, invarian, kebijakan kurs: BI = kurs tengah, KMK =
  rentang berlaku, JPY per 100 via quoted_unit).
- tools/verify-multicurrency.ps1 mengikuti pola tools/verify-*.ps1.
~~~

---

## 5. Checklist setelah setiap fase (manusia)

1. Baca `git diff` file berisiko: `PostingService`, `FxPostingService`, service alokasi, service reversal.
2. Jalankan ulang suite sendiri; bandingkan `fx:baseline` dengan Fase 0 (data IDR harus identik byte-for-byte).
3. Komit per fase ke `feat/multicurrency`.
4. Hanya lanjut bila bagian "MENUNGGU APPROVAL" kosong atau sudah Anda putuskan.

**Rollback:** matikan `platform.multicurrency_enabled` (kolom aditif, default IDR → perilaku identik). `down()` migrasi menolak bila ada data valas.

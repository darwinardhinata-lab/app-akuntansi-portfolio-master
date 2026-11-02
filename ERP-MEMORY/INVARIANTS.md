# INVARIANTS.md — Aturan yang TIDAK BOLEH DILANGGAR

> Last Updated: 2026-11-02
> Verified By: **[Nama Reviewer]**
> Status: BINDING — semua AI model WAJIB mematuhi
> Referensi tambahan: `d:/Project ERP/Prompt_Cline_Integrasi_Filament_Livewire_20261027.txt` (27 Okt 2026) dan `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (26 Okt 2026)

---

## AKUNTANSI & LEDGER

### INV-01: Satu Ledger, Satu PostingService
- Semua modul mengirim `AccountingIntent` ke `PostingService`
- **JANGAN** menulis SQL langsung ke `journal_headers` / `journal_details`
- **JANGAN** membuat ledger baru atau tabel jurnal paralel
- **JANGAN** membuat jurnal engine baru

### INV-02: Model Matriks Dipertahankan
- Input matriks: tanggal, nomor bukti, deskripsi, pihak, dimensi, blok akun
- **JANGAN** mengganti dengan general entry Addonan
- Urutan kolom dan shortcut dibekukan melalui baseline
- Baris kosong **BUKAN** nilai nol; debit dan kredit tidak boleh terisi bersamaan

### INV-03: Jurnal POSTED Immutable
- Jurnal POSTED tidak boleh dihapus untuk koreksi biasa
- Koreksi **WAJIB** melalui jurnal reversal
- PP koreksi menyimpan alasan dan rujukan ke dokumen awal
- `accounting_posting_intents.sealed_at` menandai posting final

### INV-04: evidence_number Traceability
- `journal_headers.evidence_number` = nomor dokumen sumber
- Setiap dokumen yang memicu jurnal **WAJIB** mengisi `evidence_number`
- `DocumentTraceController::trace()` harus bisa resolve semua prefix
- **JANGAN** mengubah format prefix tanpa update `DocumentTraceController`

### INV-05: Balance Validation
- Setiap jurnal **WAJIB** balance (debit = kredit)
- Validasi balance di **SATU** helper terpusat, bukan tersebar
- Jika balance gagal → rollback seluruh transaksi

---

## PAYMENT PLAN

### INV-06: Payment Plan BUKAN Buku Besar Kedua
- Payment Plan = workflow otorisasi + bukti
- Ledger = ERP Marvel-Dard
- **JANGAN** menjadikan Payment Plan sebagai sumber kebenaran saldo

### INV-07: Dua Approval Direktur untuk Bank
- Setiap payment bank **WAJIB** dua approval Direktur berbeda
- Direktur yang approve **BUKAN** pemohon
- **JANGAN** membuat flow yang bypass approval ini

### INV-08: Maker/GM Separation
- Maker = finance staff yang menyiapkan settlement
- GM = general manager yang release
- **JANGAN** gabungkan role ini dalam satu aksi

### INV-09: Paid Evidence
- Status PAID hanya setelah bukti debit aktual ada
- **JANGAN** mark PAID tanpa evidence
- **JANGAN** mark PAID jika journal posting gagal

### INV-10: Historical Read-Only
- Data historis **IMMUTABLE**
- Frozen reports tidak boleh di‑regenerate
- Historical projection hanya membaca, tidak menulis

---

## FINANCIAL FENCE & CONCURRENCY

### INV-11: Company Scope
- **SETIAP** query finansial **WAJIB** filter by `company_id`
- Cross‑company access **DITOLAK** (403)
- Tidak ada query tanpa company scope untuk data finansial

### INV-12: Expected Version Fence
- Setiap mutation finansial membawa `expected_version`
- Jika version mismatch → 409 Conflict
- **JANGAN** bypass version check

### INV-13: Idempotency Key
- Setiap mutasi finansial **WAJIB** punya idempotency key unik
- Duplicate request dengan key sama → return hasil existing
- **JANGAN** double‑allocate, double‑post, double‑reserve

### INV-14: Reservation Integrity
- Reservation hanya dilepas setelah **paid** ATAU verified no‑debit
- **JANGAN** release reservation sebelum lifecycle sah
- **JANGAN** double‑reserve

### INV-15: BCMath Only
- Semua perhitungan finansial **WAJIB** BCMath
- `bcadd()`, `bcsub()`, `bcmul()`, `bcdiv()`, `bccomp()` dengan scale 2
- **JANGAN** pernah: `+`, `-`, `*`, `/` pada string jumlah
- **JANGAN** pernah: cast ke float untuk uang

---

## DATABASE & MIGRATION

### INV-16: Migration Additive
- Migration **SELALU** additive dan backward‑compatible
- **JANGAN** drop kolom/tabel tanpa approval
- **JANGAN** rename kolom tanpa expand/contract pattern
- Feature flag default OFF untuk fitur baru

### INV-17: Decimal(20,2) for Money
- Semua kolom uang: `decimal(20,2)`
- **JANGAN** `float`, `double`, `real` untuk uang
- **JANGAN** `varchar` untuk jumlah yang akan dihitung

### INV-18: No Direct Journal SQL
- **JANGAN** `INSERT INTO journal_headers` langsung
- **JANGAN** `UPDATE journal_details` langsung
- Semua via `PostingService` → `AccountingIntent`

---

## UI & SECURITY

### INV-19: Server‑Side Authorization
- Hidden button **BUKAN** otorisasi
- Setiap action divalidasi ulang di server
- Role/permission check di **SETIAP** endpoint
- **JANGAN** percaya input client untuk perhitungan finansial

### INV-20: Private Attachment
- Attachment payment/reconciliation di **PRIVATE** storage
- **JANGAN** public storage
- SHA‑256 hash **WAJIB** dihitung server‑side
- Download **WAJIB** via authorized endpoint dengan company scope check

### INV-21: Audit Trail
- Setiap mutation finansial **WAJIB** tercatat di audit
- Audit = append‑only, tidak boleh dihapus
- Actor, timestamp, action, source **WAJIB** tercatat

---

## FILAMENT & LIVEWIRE (INV-28 s.d. INV-36)

### INV-28: Filament adalah Adapter Presentasi
- Filament Resource/Page/Action atau HTTP Controller → application use case → domain + authorization + persistence transaction → event/outbox → PostingService
- Domain, PostingService, GRN, policy dan master existing tetap otoritas
- Filament JANGAN mengganti backend atau menurunkan Laravel untuk cocok dengan komponen UI

### INV-29: Panel Terpisah dan Feature Flag
- Panel di path terpisah yang tidak berbenturan (usulan `/erp-panel` dengan ID `erp`)
- Flag config `PLATFORM_FILAMENT_ENABLED` default `false` pada production, `true` pada acceptance staging
- Flag membatasi GET panel dan update/upload/export Livewire terkait
- Cukup menyembunyikan menu TIDAK CUKUP — harus difilter oleh flag

### INV-30: Reuse Auth dan Company Scope
- Reuse web/session guard, user dan company membership ERP
- Jangan membuat user admin tambahan, auth database, role atau tenant master paralel
- Jangan mengandalkan akses permisif local environment atau gelar ADMIN sebagai izin workflow
- `canAccessPanel` atau adapter setara sesuai versi, ditambah policy aksi
- Verifikasi membership/active status pada setiap request reaktif dan saat mutasi

### INV-31: Tidak Ada RBAC Parallel
- Reuse permission Platform, tidak memasang plugin RBAC lain atau membuat wildcard permission
- Batasi hak panel, master, laporan dan aksi finansial secara terpisah
- ADMIN tidak otomatis mendapat hak pembayaran

### INV-32: Isolasi Asset CSS/JS
- Isolasi CSS/JS Filament/Tailwind pada panel
- Layout ERP lama memakai Bootstrap; jangan menambahkan dua framework CSS ke semua halaman
- Hindari Alpine ganda dan handler dobel
- Jangan menjalankan scaffold yang menimpa layout existing

### INV-33: Dashboard — Sumber Nyata, Tanpa Demo Data
- Dashboard menampilkan ringkasan yang sudah memiliki sumber/query nyata
- Jangan menciptakan saldo, data demo atau nilai nol yang menyiratkan sumber sudah tersedia
- Sumber belum terhubung diberi status "belum tersedia"
- Pisahkan IDR/USD, bank/petty, planned/actual serta historical/operational
- Cache scoped dan berumur jelas; otorisasi serta dana tersedia selalu diperiksa ulang saat aksi

### INV-34: Master First — Satu Irisan Lengkap
- Pilih satu master nonfinansial existing yang aman setelah discovery singkat (misal: unit atau divisi)
- Jangan membuat master duplikat hanya untuk demo
- Bangun satu irisan lengkap: list/detail/create/update dengan validation, authorization, service calls, lookup dan error handling
- Delete hanya jika policy/domain mengizinkan dan tidak merusak referensi; disable bukan pengganti guard server

### INV-35: Approval dan Action — Tidak Bypass State Machine
- Filament Action memanggil application use case yang juga digunakan WorkflowController
- Aktor berasal dari sesi terverifikasi, bukan parameter form
- `expected_version` dan mutation ID disampaikan secara konsisten; retry tidak menggandakan keputusan/event
- Tombol yang belum memiliki implementasi backend aman ditampilkan disabled dengan alasan atau belum ditampilkan
- Jangan mengubah state langsung melalui `model->update()`
- Bulk approval/posting/deletion tidak diaktifkan sampai semantics per-item, SoD, atomicity dan retry diuji
- Jangan mengizinkan bypass state machine atau model mutation lewat inline toggle, editable table column, relation action atau bulk action

### INV-36: Livewire Security — Public Properties sebagai Input Tidak Tepercaya
- Public properties Livewire, record IDs, role, company ID, price/status, source account dan event ref dianggap input tidak tepercaya
- Server mengambil ulang record dan authoritative totals dalam transaksi
- Setup guard/rate limit/session timeout/CSRF berlaku pada API, Livewire update dan upload
- Error 401/403/409/419/422/429 dapat dipahami UI dan tidak menyimpan state palsu
- Parameter sorting/filter dan expression tidak boleh menjadi raw SQL dari input
- Gunakan server pagination, eager loading dan query terukur
- Chart total tidak menjumlah currencies berbeda

### INV-22: R1 Scope
R1 = bank IDR, jasa langsung, AP parsial, single‑company MGI
**DEFERRED**: USD, DP/progress, retention, petty cash, multi‑company

### INV-23: Tidak Mengubah Fondasi
**JANGAN** mengubah:
- PostingService existing
- V33ApprovalDomain state machine
- Financial fence mechanism
- Reservation logic existing
- Journal matrix input
- Workflow approval v33

### INV-24: Tidak Membuat Paralel
**JANGAN** membuat:
- Workflow payment kedua/paralel
- Ledger baru
- Journal engine baru
- Generic CRUD untuk mengubah paid/posted
- Auto‑approval tanpa evidence

---

## PROCESS

### INV-25: Discovery Before Coding
- **JANGAN** asumsikan struktur tabel/route
- Baca source aktual **SEBELUM** menulis patch
- Jika berbeda dari dokumentasi → laporkan, jangan tebak

### INV-26: Test Before Claim
- **JANGAN** klaim PASS tanpa menjalankan test
- Setiap hasil test **WAJIB**: environment, perintah, expected, actual, tanggal
- Jika tidak bisa dijalankan → tulis NOT RUN + alasan

### INV-27: No Production Changes
- **JANGAN** merge ke main tanpa approval
- **JANGAN** deploy production tanpa approval
- **JANGAN** jalankan migration di DB operasional tanpa approval
- **JANGAN** transfer bank nyata
- **JANGAN** ubah COA production

## B01–B12 IMPLEMENTATION (INV-37 s.d. INV-42)

### INV-37: Discovery Sebelum Coding (B01)
- Buktikan baseline sebelum modifikasi
- Inventaris route, controller, model, migration, policy, service, UI dan test yang relevan
- Verifikasi apakah A3 GRN/posting protection sudah ada; jangan mengasumsikan commit lama masih HEAD
- Buat peta source file + fungsi + tabel + event

### INV-38: Master Data Crosswalk (B02)
- Satu identitas perusahaan, user, COA, supplier/customer, material dan bank
- Tidak mengimpor master tanpa crosswalk
- Crosswalk: `company_id`, `source_system`, `entity_type`, `source_id`, `target_id`, `raw_code`, `status`, `confidence`, `approved_by`, `approved_at`
- Unique pada `company_id` + `source_system` + `entity_type` + `source_id`
- Status `unresolved` memblokir aktivasi, bukan menghapus baris
- Kode akun display tidak boleh menjadi satu-satunya foreign key

### INV-39: Posting Adapter (B03)
- Semua modul memakai mesin jurnal ERP yang sama tanpa mengganti UI matriks
- Definisikan `AccountingIntent` dengan `company_id`, `source_type`, `source_id`, `event_type`, `event_version`, `currency`, `rate`, `date`, `amounts`, `dimensions`
- Idempotency key unik dan payload hash
- `journal_links`, posted immutability, period lock serta reversal link
- Key sama + hash sama → return journal lama; hash berbeda → konflik

### INV-40: Payment Plan Parity (B04)
- Integrasi domain v33 secara setara
- Ganti identity Sites dengan sesi/SSO ERP yang terverifikasi
- Map DB/R2 melalui adapter; pertahankan expected version dan financial mutation fence
- Tambahkan company scope pada data/query/blob dan policy v33
- Jangan mengganti jurnal matriks dengan general entry Addonan
- Jangan menjadikan Payment Plan sebagai buku besar kedua

### INV-41: Saldo, Allocation dan Posting Payment (B05)
- Payment account policy effective date dengan default perilaku v33 (IDR 3 / USD 1)
- Jangan mengganti berdasarkan memori lama
- Reservation atomik per rekening/mata uang saat instruksi siap
- Account balance aktual tetap terpisah dari reservation
- Gunakan party/invoice/contract IDs untuk allocation AP, DP, progress, final dan retention
- Ganti `post_external` dengan journal adapter acknowledgment
- Fee/withholding terpisah dan multi-COA allocations harus balance

### INV-42: UAT, Cutover dan Rollback (B11–B12)
- Go hanya jika seluruh P0 ditutup, UAT kritis lulus, restore DB+blob terbukti, saldo dan quantities direkonsiliasi, rekening sumber disahkan, personel role sudah ditugaskan
- Feature flag per modul default off sampai gate lulus
- Rollback tidak boleh restore lama tanpa replay/reconciliation delta setelah ada transaksi baru
- Sajikan diff dan instruksi konkret untuk persetujuan operasional
- Jangan mengubah server produksi tanpa otorisasi


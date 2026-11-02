# INVARIANTS.md — Aturan yang TIDAK BOLEH DILANGGAR

> Last Updated: 2026-10-30
> Verified By: **[Nama Reviewer]**
> Status: BINDING — semua AI model WAJIB mematuhi

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

## SCOPE BOUNDARIES

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


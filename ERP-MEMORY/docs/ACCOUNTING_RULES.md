# ACCOUNTING_RULES.md — Aturan Akuntansi Detail ERP Marvel-Dard

> Last Updated: 2026-10-30  
> Branch: `feat/marvel-dard-integration`  
> Status: BINDING — semua AI model WAJIB mematuhi

---

## 1. MATRIX INPUT FORMAT

Input jurnal menggunakan format matriks (tidak general entry bebas):

| Kolom | Deskripsi | Contoh |
|-------|-----------|--------|
| `tanggal` | Transaksi date | `2026-10-30` |
| `nomor_bukti` | Nomor dokumen sumber | `INV-2026-001` |
| `deskripsi` | Keterangan transaksi | `Pembayaran invoice supplier` |
| `pihak` | Counterparty (supplier/customer) | `PT. Supplier Abadi` |
| `dimensi` | Dimensi analitik (cost center, dept, project) | `DIV-001` |
| `blok_akun` | Blok akun COA | `111` (kas), `211` (AP) |
| `debit` | Nilai debit (BCMath string) | `1000000.00` |
| `kredit` | Nilai kredit (BCMath string) | `0.00` |

### Aturan Matriks (INV-02)
- Baris kosong **BUKAN** nilai nol; baris kosong diabaikan
- Debit dan kredit **tidak boleh** terisi bersamaan pada satu baris
- Urutan kolom dan shortcut dibekukan melalui baseline
- Jangan mengganti dengan general entry Addonan

---

## 2. POSTING PIPELINE

Semua posting melalui `PostingService` → `AccountingIntent` (INV-01, INV-18):

```
1. Service menyusun AccountingIntent:
   - company_id (wajib)
   - evidence_number = nomor dokumen sumber (wajib, INV-04)
   - lines = array matriks [debit, kredit, account_code, ...]
   - expected_version (fence, INV-12)
   - idempotency_key (unik, INV-13)

2. PostingService::post(AccountingIntent):
   a. Validasi company scope (INV-11)
   b. Validasi expected_version fence (INV-12)
   c. Validasi idempotency key tidak duplikat (INV-13)
   d. Validasi balance: Σdebit = Σkredit (INV-05, satu helper terpusat)
   e. Tulis journal_headers + journal_details via ORM
   f. Tulis accounting_posting_intents (sealed_at = null dulu)
   g. Set sealed_at setelah berhasil
   h. Return journal_id atau RuleViolation (409)

3. Jika gagal di langkah manapun → rollback seluruh transaksi
```

---

## 3. CHART OF ACCOUNTS (COA)

- Semua akun menggunakan kode numerik (blok akun)
- COA production **tidak boleh diubah** tanpa approval (INV-23)
- Blok akun umum (contoh):
  - `1xx` — Aset (111 Kas, 112 Bank, 121 Piutang)
  - `2xx` — Kewajiban (211 AP, 221 Pajak)
  - `3xx` — Ekuitas
  - `4xx` — Pendapatan
  - `5xx` — Beban
- Setiap baris jurnal harus merujuk ke kode akun yang valid di COA

---

## 4. PERIOD LOCK

- Jurnal tidak boleh diposting ke periode yang sudah dikunci (closed period)
- Periode dikunci setelah laporan keuangan ditandatangani
- Upaya posting ke periode terkunci → 409 Conflict
- Pembukaan periode kembali memerlukan approval manajemen

---

## 5. REVERSAL (PEMBATALAN JURNAL)

### Ketika Digunakan
- Jurnal POSTED **tidak boleh dihapus** untuk koreksi biasa (INV-03)
- Koreksi **WAJIB** melalui jurnal reversal (mirror balik debit/kredit)

### Aturan Reversal
- Reversal jurnal mencatat alasan dan rujukan ke dokumen awal
- `accounting_posting_intents.sealed_at` menandai posting final
- Reversal membuat jurnal baru, bukan mengubah yang lama
- Reversal juga melalui `PostingService` → `AccountingIntent`

---

## 6. FINANCIAL FENCE

| Fence | Deskripsi | Referensi |
|-------|-----------|-----------|
| Company scope | Setiap query finansial filter `company_id` | INV-11 |
| Expected version | Setiap mutation membawa `expected_version` | INV-12 |
| Idempotency key | Setiap mutasi punya key unik | INV-13 |
| Balance validation | Debit = kredit, satu helper terpusat | INV-05 |
| Evidence number | `evidence_number` = nomor dokumen sumber | INV-04 |
| Period lock | Tidak bisa posting ke periode terkunci | Section 4 |
| Immutable posted | Jurnal POSTED tidak dihapus, koreksi via reversal | INV-03 |

---

## 7. PERHITUNGAN FINANSIAL (BCMath)

- Semua kolom uang: `decimal(20,2)` (INV-17)
- Semua perhitungan: BCMath (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`), scale 2 (INV-15)
- **DILARANG**: `+`, `-`, `*`, `/` pada string jumlah
- **DILARANG**: cast ke `float` untuk uang
- **DILARANG**: `number_format()` sebelum save ke DB

---

## 8. TABEL KEUANGAN UTAMA

| Tabel | Kolom Kunci | Tipe | Catatan |
|-------|-------------|------|---------|
| `journal_headers` | `journal_id`, `company_id`, `evidence_number`, `currency_code`, `status` | PK, FK, string, string, enum | status: DRAFT/POSTED |
| `journal_details` | `id`, `journal_header_id`, `account_code`, `debit`, `credit` | PK, FK, string, decimal, decimal | balance check |
| `accounting_posting_intents` | `id`, `company_id`, `idempotency_key`, `journal_id`, `sealed_at` | PK, FK, string, FK, timestamp | audit trail |
| `purchase_bills` | `id`, `company_id`, `grand_total`, `outstanding_amount`, `allocated_amount`, `journal_id` | PK, FK, decimal, decimal, decimal, FK | AP tracking |

_Last Updated: 2026-10-30_


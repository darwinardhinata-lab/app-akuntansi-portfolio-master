# DATABASE_RELATIONSHIPS.md — Relasi Tabel Kritis ERP Marvel-Dard

> Last Updated: 2026-10-30  
> Branch: `feat/marvel-dard-integration`  
> Konvensi: semua kolom uang `decimal(20,2)`, tabel `snake_case` dengan prefix modul.

---

## 1. DAFTAR TABEL UTAMA

| Tabel | Modul | Deskripsi |
|-------|-------|-----------|
| `companies` | Core | Master perusahaan (multi-tenant scope) |
| `users` | Core | User & role (accountant, director, GM, maker) |
| `journal_headers` | Finance/Ledger | Header jurnal umum (POSTED immutable) |
| `journal_details` | Finance/Ledger | Baris jurnal (debit/kredit per akun) |
| `accounting_posting_intents` | Finance/Ledger | Audit trail + idempotency untuk setiap posting |
| `purchase_orders` | Purchasing | Purchase order (head) |
| `purchase_order_details` | Purchasing | Baris PO (item, qty, price) |
| `purchase_receipts` | Purchasing | Goods receipt note (GRN) |
| `purchase_receipt_details` | Purchasing | Baris GRN |
| `purchase_bills` | Purchasing / AP | Invoice dari supplier |
| `ppl_requests` | Payment Plan | Permintaan pembayaran (workflow) |
| `ppl_ap_payment_allocations` | Payment Plan | Alokasi pembayaran ke AP invoice |
| `ppl_events` | Payment Plan | Event log alokasi (idempotency + audit) |

---

## 2. RELASI KRITIS (FOREIGN KEY & CASCADE)

### 2.1 Jurnal & Ledger

```
companies (1) ──< journal_headers (many)
    FK: journal_headers.company_id → companies.id
    On delete: restrict

journal_headers (1) ──< journal_details (many)
    FK: journal_details.journal_header_id → journal_headers.id
    On delete: restrict (jurnal POSTED immutable, hapus via reversal)

journal_headers (1) ──< accounting_posting_intents (1)
    FK: accounting_posting_intents.journal_id → journal_headers.id
    On delete: restrict
```

### 2.2 Purchasing → AP

```
purchase_orders (1) ──< purchase_order_details (many)
    FK: purchase_order_details.purchase_order_id → purchase_orders.id

purchase_orders (1) ──< purchase_receipts (many)
    FK: purchase_receipts.purchase_order_id → purchase_orders.id

purchase_receipts (1) ──< purchase_receipt_details (many)
    FK: purchase_receipt_details.purchase_receipt_id → purchase_receipts.id

purchase_orders (1) ──< purchase_bills (many)
    FK: purchase_bills.purchase_order_id → purchase_orders.id
    (bisa NULL: direct service tanpa PO)

purchase_bills (1) ──< journal_headers (1, opsional)
    FK: purchase_bills.journal_id → journal_headers.id
    (diisi setelah bill diposting ke jurnal)
```

### 2.3 Payment Plan → AP

```
companies (1) ──< ppl_requests (many)
    FK: ppl_requests.company_id → companies.id

ppl_requests (1) ──< ppl_ap_payment_allocations (many)
    FK: ppl_ap_payment_allocations.payment_request_id → ppl_requests.id

companies (1) ──< ppl_ap_payment_allocations (many)
    FK: ppl_ap_payment_allocations.company_id → companies.id

purchase_bills (1) ──< ppl_ap_payment_allocations (many)
    FK: ppl_ap_payment_allocations.purchase_bill_id → purchase_bills.id

ppl_ap_payment_allocations (1) ──< ppl_events (many)
    FK: ppl_events.mutation_id → ppl_ap_payment_allocations.idempotency_key
    (logis, bukan FK fisik di semua implementasi)
```

---

## 3. INDEKS PENTING

| Tabel | Index | Kolom | Tujuan |
|-------|-------|-------|--------|
| `journal_headers` | `idx_jh_company_date` | `company_id, created_at` | Query jurnal per perusahaan/tanggal |
| `journal_headers` | `idx_jh_evidence` | `evidence_number` | Trace dokumen sumber (INV-04) |
| `purchase_bills` | `idx_pb_company_po` | `company_id, purchase_order_id` | Lookup bill per PO |
| `purchase_bills` | `idx_pb_journal` | `journal_id` | Cari bill yang sudah diposting |
| `ppl_ap_payment_allocations` | `idx_alloc_company_bill` | `company_id, purchase_bill_id` | Alokasi per bill |
| `ppl_ap_payment_allocations` | `idx_alloc_request` | `payment_request_id` | Alokasi per request |
| `ppl_ap_payment_allocations` | `idx_alloc_idem` | `idempotency_key` (UNIQUE) | Cegah duplikasi alokasi |

---

## 4. ATURAN FOREIGN KEY

| Aturan | Detail |
|--------|--------|
| **Restrict on delete** | Tabel referensi (companies, purchase_orders, purchase_bills, journal_headers) tidak boleh dihapus jika masih dirujuk |
| **Cascade tidak diizinkan** pada data finansial | Hapus harus melalui reversal, bukan hard delete |
| **UUID untuk ppl_requests** | `payment_request_id` bertipe UUID, bukan auto-increment |
| **Company scope wajib** | Setiap tabel finansial harus memiliki `company_id` |

---

## 5. MIGRASI TERKAIT (2026-10-30)

| File | Tabel | Aksi |
|------|-------|------|
| `2026_10_30_000001_add_three_way_match_to_purchase_bills` | `purchase_bills` | Tambah kolom 3-way match |
| `2026_10_30_000001_add_three_way_match_to_purchase_invoices` | `purchase_invoices` | Tambah kolom 3-way match |
| `2026_10_30_000002_add_ap_outstanding_tracking` | `purchase_bills` | Tambah tracking outstanding |
| `2026_10_30_000002_add_ap_outstanding_tracking_to_purchase_bills` | `purchase_bills` | Tracking outstanding (dup) |
| `2026_10_30_000003_create_ap_payment_allocations` | `ppl_ap_payment_allocations` | Buat tabel alokasi |
| `2026_10_30_000003_create_ppl_ap_payment_allocations` | `ppl_ap_payment_allocations` | Buat tabel alokasi (dup) |

> Catatan: beberapa migrasi memiliki nama duplikat dengan suffix `X` (`.phpX`). Perlu dibersihkan sebelum deploy production.

_Last Updated: 2026-10-30_


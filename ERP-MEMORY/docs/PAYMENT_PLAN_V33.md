# PAYMENT_PLAN_V33.md — Kontrak & Aturan Payment Plan v33

> Last Updated: 2026-10-30  
> Branch: `feat/marvel-dard-integration`  
> Status: BINDING untuk semua perubahan Payment Plan / Finance

---

## 1. IDENTITAS PAYMENT PLAN

Payment Plan v33 adalah **workflow otorisasi + bukti pembayaran**, bukan buku besar kedua.

| Aspek | Nilai |
|-------|-------|
| Tujuan | Workflow approval pembayaran bank (IDR, R1 scope) |
| Sumber kebenaran saldo | ERP Marvel-Dard ledger (`journal_headers`) |
| Modul | `App\Modules\PaymentPlan\*` |
| Route prefix | `kebab-case`, prefix modul |
| Currency R1 | IDR saja (USD deferred) |

---

## 2. STATUS & WORKFLOW (STATE MACHINE)

Setiap `ppl_requests` memiliki siklus hidup berikut:

```
draft → submitted → request_approved → settlement_review → settlement_approved
   ↓                                                      ↓
bank_prepared ←──────────────────────────────────────────┘
   ↓
bank_hold ←──────────────────────────────────────────────┘
   ↓
bank_released ←──────────────────────────────────────────┘
   ↓
paid (setelah bukti debit aktual)
   ↓
posted (setelah jurnal ERP diakui)
   ↓
rejected (dari mana saja, oleh Direktur)
```

### Transisi dan Role yang Diizinkan

| Dari → Ke | Role yang Diizinkan | Syarat |
|-----------|---------------------|--------|
| draft → submitted | Finance staff (maker) | Data lengkap, validasi dasar |
| submitted → request_approved | Direktur (bukan pemohon) | Approval #1 |
| request_approved → settlement_review | Finance staff | Siapkan settlement |
| settlement_review → settlement_approved | Direktur (bukan pemohon) | Approval #2 |
| settlement_approved → bank_prepared | Maker (finance staff) | Buat instruksi bank |
| bank_prepared → bank_released | GM | Release ke bank |
| bank_released → paid | Finance staff | Bukti debit aktual ada |
| paid → posted | Finance staff | Jurnal ERP diakui |
| apa saja → rejected | Direktur | Alasan wajib dicatat |

---

## 3. ATURAN APPROVAL (INV-07, INV-08)

### INV-07: Dua Approval Direktur untuk Bank
- Setiap payment bank **WAJIB** dua approval Direktur yang berbeda
- Direktur yang approve **BUKAN** pemohon request
- Jangan membuat flow yang bypass approval ini

### INV-08: Maker/GM Separation
- Maker = finance staff yang menyiapkan settlement
- GM = general manager yang release bank
- Jangan gabungkan role ini dalam satu aksi

---

## 4. INDEMPOTENCY & IDEMPOTENCY KEY

Setiap alokasi pembayaran wajib punya idempotency key:

```
Format: ppl:ap:alloc:<company_id>:<invoice_id>:<amount>:<mutation_id>
```

- Duplicate request dengan key sama → return hasil existing (tidak double allocate)
- Key harus unik per mutasi finansial
- Simpan di `ppl_ap_payment_allocations.idempotency_key` (UNIQUE index)

---

## 5. ALLOCATION RULES (AP)

| Aturan | Detail |
|--------|--------|
| IDR only (R2 no auto-FX) | Invoice harus IDR, non-IDR → 409 `non_idr_invoice_not_supported` |
| Outstanding check | `bccomp($amount, $outstanding, 2)` tidak boleh > 0 |
| 3-way match | Bill butuh PO, GRN completed, dan journal posted |
| Journal currency | Journal harus IDR (`currency_code !== 'IDR'` → 409) |
| Idempotency | Cek `ppl_ap_payment_allocations` sebelum insert baru |
| Update bill outstanding | `allocated_amount` dan `outstanding_amount` diupdate setelah alokasi |
| Event log | Setiap alokasi emit event ke `ppl_events` |

---

## 6. KONTRAK API v33 (RINGKASAN)

| Endpoint | Method | Deskripsi | Auth |
|----------|--------|-----------|------|
| `/erp-panel/bank-payments` | GET | List payment requests | Finance role |
| `/erp-panel/bank-payments` | POST | Buat request baru (draft) | Maker |
| `/erp-panel/bank-payments/{id}/approve` | POST | Direktur approve request | Direktur |
| `/erp-panel/bank-payments/{id}/settle` | POST | Siapkan settlement | Maker |
| `/erp-panel/bank-payments/{id}/release` | POST | GM release bank | GM |
| `/erp-panel/bank-payments/{id}/paid` | POST | Konfirmasi debit aktual | Finance |
| `/erp-panel/bank-payments/{id}/post` | POST | Acknowledge jurnal ERP | Finance |
| `/erp-panel/bank-payments/{id}/reject` | POST | Reject (alasan wajib) | Direktur |

---

## 7. BUKTI & AUDIT

- Status `paid` hanya setelah bukti debit aktual ada (INV-09)
- Jangan mark `paid` tanpa evidence
- Jangan mark `paid` jika journal posting gagal
- Setiap mutation finansial wajib tercatat di audit trail (append-only)
- Attachment payment/reconciliation di private storage, SHA-256 server-side (INV-20)

---

## 8. SCOPE TERBATAS (R1)

| Termasuk (R1) | Ditunda (Setelah R1) |
|---------------|----------------------|
| Bank IDR | USD payment |
| Jasa langsung (direct service) | DP / Progress / Retention |
| AP parsial (by purchaseBillId) | Petty cash |
| Single-company MGI | Multi-company |

---

## 9. CARA MENMBAH FITUR BARU

1. Baca `INVARIANTS.md` (INV-06 sampai INV-10)
2. Baca `docs/ARCHITECTURE.md` dan `docs/ACCOUNTING_RULES.md`
3. Buat migration (additive, backward-compatible, feature flag default OFF)
4. Implement service di `App\Modules\PaymentPlan\Services\*`
5. Tambah route + controller + validasi
6. Tulis test di `tests/Feature/PaymentPlan/`
7. Jalankan test sebelum klaim PASS (INV-26)
8. Commit dan catat keputusan di `docs/CHANGE_DECISIONS.md`

---

_Last Updated: 2026-10-30_
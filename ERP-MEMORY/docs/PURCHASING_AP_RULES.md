# PURCHASING_AP_RULES.md — Aturan Purchasing & AP

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Modul: `App\Modules\Purchasing\*`

---

## 1. RUANG LINGKUP

Modul Purchasing & AP menangani:
- Purchase Order (PO)
- Goods Receipt Note (GRN) / Purchase Receipt
- Purchase Bill (invoice dari supplier)
- 3-Way Match (PO ↔ GRN ↔ Bill)
- AP Outstanding Tracking
- AP Payment Allocation (berhubungan dengan Payment Plan)

---

## 2. TABEL UTAMA

| Tabel | Deskripsi |
|---|---|
| `purchase_orders` | Header PO |
| `purchase_order_details` | Baris PO (item, qty, price) |
| `purchase_receipts` | GRN / terima barang |
| `purchase_receipt_details` | Baris GRN |
| `purchase_bills` | Invoice supplier |

---

## 3. 3-WAY MATCH (VALIDASI SEBELUM POSTING)

Sebelum purchase bill dapat diposting ke jurnal, harus lolos 3-way match:

```
PO (Purchase Order) ──┐
                      ├──► 3-WAY MATCH ──► Jika cocok → Boleh posting
GRN (Purchase Receipt)┤                      Jika tidak → RuleViolation
                      └► Bill (Purchase Bill)
```

### Kriteria Match
1. **Bill harus terhubung ke PO** (`purchase_bills.purchase_order_id` tidak boleh NULL)
2. **PO harus memiliki GRN yang COMPLETED/RECEIVED** (`purchase_receipts.status` IN `['COMPLETED', 'RECEIVED']`)
3. **Bill harus memiliki journal_id** yang sudah diposting dan currency-nya IDR
4. **Toleransi qty dan price** — beda dalam batas `match_tolerance_qty` dan `match_tolerance_price`

### RuleViolation yang Muncul
- `no_purchase_order_linked` — bill tidak punya PO
- `no_completed_grn` — PO tidak punya GRN selesai
- `no_posted_journal` — bill tidak punya jurnal posted
- `journal_not_found` — journal_id tidak ditemukan
- `non_idr_currency_not_supported` — journal bukan IDR

---

## 4. AP OUTSTANDING TRACKING

Setiap purchase bill melacak outstanding amount:

| Kolom | Deskripsi |
|---|---|
| `grand_total` | Total tagihan bill |
| `allocated_amount` | Jumlah yang sudah dialokasi pembayaran |
| `outstanding_amount` | `grand_total - allocated_amount` |
| `payment_allocation_count` | Jumlah kali alokasi dilakukan |

### Aturan
- `outstanding_amount` tidak boleh negatif
- Setiap alokasi baru mengurangi `outstanding_amount`
- Alokasi tidak boleh melebihi outstanding (`bccomp($amount, $outstanding, 2) > 0` → RuleViolation)

---

## 5. AP PAYMENT ALLOCATION (BERHUBUNGAN DENGAN PAYMENT PLAN)

Alokasi pembayaran ke AP invoice diatur di `docs/PAYMENT_PLAN_V33.md` dan `App\Modules\PaymentPlan\Services\ApAllocationService.php`.

### Aturan Utama
- Hanya invoice IDR yang didukung (R2 no auto-FX)
- Setiap alokasi butuh idempotency key unik
- Duplikat idempotency key → return hasil existing (tidak double allocate)
- Setiap alokasi emit event ke `ppl_events`
- Tabel: `ppl_ap_payment_allocations`

---

## 6. ATURAN UMUM

| Aturan | Detail |
|---|---|
| Company scope | Setiap query harus filter `company_id` |
| No direct journal SQL | Semua posting via `PostingService` |
| BCMath | Semua perhitungan uang menggunakan BCMath scale 2 |
| Migration additive | Migrasi harus additive & backward-compatible |
| Immutable posted | Jurnal POSTED tidak dihapus, koreksi via reversal |

_Last Updated: 2026-11-02_
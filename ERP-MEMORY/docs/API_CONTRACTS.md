# API_CONTRACTS.md — Kontrak API ERP Marvel-Dard

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Format: Route | Method | Deskripsi | Auth / Role

---

## 1. PAYMENT PLAN (PPL)

| Endpoint | Method | Deskripsi | Role |
|---|---|---|---|
| `/erp-panel/bank-payments` | GET | Daftar payment request | Finance |
| `/erp-panel/bank-payments` | POST | Buat request baru (draft) | Maker |
| `/erp-panel/bank-payments/{id}` | GET | Detail request | Finance |
| `/erp-panel/bank-payments/{id}/approve` | POST | Direktur approve request | Direktur (bukan pemohon) |
| `/erp-panel/bank-payments/{id}/settle` | POST | Siapkan settlement | Maker |
| `/erp-panel/bank-payments/{id}/release` | POST | GM release bank | GM |
| `/erp-panel/bank-payments/{id}/hold` | POST | GM hold bank | GM |
| `/erp-panel/bank-payments/{id}/paid` | POST | Konfirmasi debit aktual | Finance |
| `/erp-panel/bank-payments/{id}/post` | POST | Acknowledge jurnal ERP | Finance |
| `/erp-panel/bank-payments/{id}/reject` | POST | Reject dengan alasan | Direktur |
| `/erp-panel/bank-payments/{id}/print` | GET | Cetak bukti pembayaran | Finance |

---

## 2. AP / PURCHASING

| Endpoint | Method | Deskripsi | Role |
|---|---|---|---|
| `/api/purchase-bills` | GET | Daftar purchase bill | Purchasing/Finance |
| `/api/purchase-bills/{id}` | GET | Detail bill | Purchasing/Finance |
| `/api/purchase-bills/{id}/allocate` | POST | Alokasi pembayaran ke bill | Finance |
| `/api/purchase-bills/{id}/three-way-match` | POST | Validasi 3-way match | Finance |

> ⚠️ Catatan: Beberapa endpoint di atas mungkin belum diimplementasikan sepenuhnya. Lakukan discovery di `routes/` dan `app/Http/Controllers/` sebelum mengandalkan.

---

## 3. ATURAN API UMUM

| Aturan | Detail |
|---|---|
| Company scope | Setiap response difilter `company_id` |
| Auth wajib | Setiap endpoint butuh autentikasi |
| Role check | Setiap endpoint memeriksa role yang diizinkan |
| Idempotency | Mutasi finansial butuh idempotency key |
| Expected version | Mutation butuh `expected_version` fence |
| Response format | JSON standar dengan status code HTTP yang benar |

---

## 4. DISCOVERY SEBELUM MENGGUNAKAN API

1. Baca `routes/web.php` dan `routes/api.php`
2. Baca `app/Http/Controllers/`
3. Baca `docs/ARCHITECTURE.md` untuk layering
4. Baca `INVARIANTS.md` untuk aturan keamanan

_Last Updated: 2026-11-02_
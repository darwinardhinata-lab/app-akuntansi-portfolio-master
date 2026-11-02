# ARCHITECTURE.md — Arsitektur Sistem ERP Marvel-Dard

> Last Updated: 2026-10-30  
> Branch: `feat/marvel-dard-integration`  
> Stack: Laravel 12, PHP 8.2, MySQL, Blade + Filament 5 + Livewire 4

---

## 1. GAMBARAN KESELURUHAN

ERP Marvel-Dard adalah monolit Laravel yang melayani modul akuntansi, manufaktur, pembelian, dan finance untuk PT. Magicase Group Indonesia. Arsitektur berbasis **modular domain** dengan satu ledger terpusat melalui `PostingService`.

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT (Browser / API)                   │
└────────────────┬────────────────────────────────────────────┘
                 │ HTTP (Blade / Filament / Livewire / REST)
┌────────────────▼────────────────────────────────────────────┐
│                      Laravel Framework                      │
│  Routing → Middleware (Auth, CompanyScope, RateLimit)       │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────┼────────────────────────────────────────────┐
│  CONTROLLER LAYER (HTTP)                                     │
│  • Validasi request, autorisasi, response                   │
│  • Tidak berisi bisnis logika finansial                      │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────┼────────────────────────────────────────────┐
│  SERVICE LAYER (Domain Services)                             │
│  • App\Modules\PaymentPlan\Services\ApAllocationService     │
│  • App\Modules\PaymentPlan\Services\PaymentPlanService      │
│  • App\Support\PostingService                                │
│  • App\Services\DocumentTraceController                      │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────┼────────────────────────────────────────────┐
│  DOMAIN LAYER (Rules & State Machines)                       │
│  • RuleViolation exceptions                                  │
│  • V33ApprovalDomain state machine                           │
│  • AccountingIntent DTOs                                     │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────┼────────────────────────────────────────────┐
│  INFRASTRUCTURE (Eloquent ORM + MySQL)                       │
│  • Models, Migrations, Factories, Seeders                    │
│  • Repository pattern (pilihan, optional)                    │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│                    MySQL Database                            │
│  • journal_headers / journal_details                         │
│  • purchase_bills, purchase_orders, purchase_receipts        │
│  • ppl_requests, ppl_ap_payment_allocations                  │
│  • accounting_posting_intents                                │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. MODUL & BATASAN (BOUNDARIES)

| Modul | Namespace | Tanggung Jawab Utama | Batasan |
|-------|-----------|----------------------|---------|
| **Finance / Ledger** | `App\Support\PostingService` | Satu ledger, semua jurnal melalui `AccountingIntent` | Tidak boleh membuat jurnal engine paralel |
| **Payment Plan (PPL)** | `App\Modules\PaymentPlan\*` | Workflow otorisasi bank, settlement, allocation | BUKAN buku besar kedua; hanya workflow + bukti |
| **Purchasing / AP** | `App\Modules\Purchasing\*` | PO, GRN, purchase bill, 3-way match | Tidak menulis jurnal langsung |
| **Manufacturing (MFG)** | `App\Modules\Manufacturing\*` | BOM, MRP, work order, costing | Native Laravel port, selesai |
| **Inventory** | `App\Modules\Inventory\*` | Stok, gudang, mutasi barang | Tidak menyentuh jurnal finansial langsung |
| **Filament UI** | `App\Filament\*` | Panel admin, master data, cetak | UI hanya, otorisasi tetap di server |

---

## 3. ALUR POSTING JURNAL (KORE ARSITEKTUR)

Semua mutasi finansial mengikuti alur berikut (INV-01, INV-18):

```
Request (Controller)
    ↓
Service membuat AccountingIntent (company_id, evidence_number, lines, expected_version, idempotency_key)
    ↓
PostingService::post(AccountingIntent)
    ↓
Validasi: balance (debit = kredit), company scope, period lock
    ↓
Tulis journal_headers + journal_details melalui ORM (jangan SQL langsung)
    ↓
Tulis accounting_posting_intents (audit trail, sealed_at)
    ↓
Kembalikan journal_id atau RuleViolation (409)
```

---

## 4. KEAMANAN & SCOPE

- **Company scope**: setiap query finansial wajib difilter `company_id` (INV-11).
- **Expected version fence**: setiap mutation membawa `expected_version`, mismatch → 409 (INV-12).
- **Idempotency**: setiap mutasi finansial wajib punya idempotency key unik (INV-13).
- **Server-side authorization**: hidden button bukan otorisasi, validasi ulang di server (INV-19).
- **Private attachment**: attachment payment/reconciliation di private storage, SHA-256 server-side (INV-20).

---

## 5. LAYER KEAMBAN (LAYERING RULES)

1. **Controller** hanya menangani HTTP: validasi input, auth, panggil service, return response.
2. **Service** berisi orkestrasi bisnis: memanggil domain rules, model, dan PostingService.
3. **Domain** berisi aturan bisnis murni: `RuleViolation`, state machine, DTO.
4. **Model/ORM** hanya menangani data access: query, relasi, mutasi baris.
5. **Jangan** melewatkan layer (mis. controller langsung menulis journal SQL).

---

## 6. TEST STRATEKTUR (RINGKASAN)

| Tipe | Lokasi | Perintah |
|------|--------|----------|
| Unit | `tests/Unit/` | `phpunit tests/Unit` |
| Feature | `tests/Feature/` | `phpunit tests/Feature` |
| Integration (MySQL) | `tests/Integration/` | `AP_MYSQL_TESTS=1 phpunit tests/Integration` |
| Lint | Semua file PHP | `php -l <file>` |

_Last Updated: 2026-10-30_


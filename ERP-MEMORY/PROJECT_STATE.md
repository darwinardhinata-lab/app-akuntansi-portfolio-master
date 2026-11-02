# PROJECT_STATE.md — Status Terkini ERP Marvel-Dard

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Commit: `df0f22b` (docs: fill ERP-MEMORY placeholders + CHANGE_DECISIONS + PAYMENT_PLAN_V33)
> Commit sebelumnya: `55e59b2` (feat: add AP three-way match and payment allocation)
> Dokumen sumber rencana: `d:/Project ERP/Prompt_Cline_Integrasi_Filament_Livewire_20261027.txt` dan `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt`

---

## STATUS KESELURUHAN

| Area | Status | Catatan |
|---|---|---|
| R1 Finance Payment UI (bank IDR) | ✅ Code-complete | 721 tests PASS |
| R1 Acceptance Hardening | 🔄 Artifacts dibuat | Belum dijalankan di staging |
| F04 AP Residual | 🔄 IN_PROGRESS | Discovery selesai, coding belum |
| F05 DP/Progress/Retention | ⏸️ DEFERRED | Setelah R1 accepted |
| F06 Pajak/Fee/Multi-account | ⏳ TODO | Setelah F04 |
| R2 Three-way match | ✅ IMPLEMENTED | Migration + ApAllocationService dibuat |
| USD Payment | ⏸️ DEFERRED | Setelah R1 |
| Petty Cash | ⏸️ DEFERRED | Setelah R1 |
| Filament Panel | 🔄 IN_PROGRESS | Canonical pages dibuat, Panel di `/erp-panel`, feature flag `PLATFORM_FILAMENT_ENABLED` default OFF |
| Manufacturing (MFG) | ✅ SELESAI | Native Laravel port |
| ERP-MEMORY Documentation | ✅ LENGKAP | Semua dokumen AI sudah terisi, termasuk B01-B12 roadmap, menu map, crosswalk |
| Filament + Livewire Adoption | 📋 PLANNED | Per `docs/FILAMENT_LIVEWIRE_ADOPTION.md`, belum diinstal di composer.json |

---

## DOKUMENTASI LENGKAP (ERP-MEMORY/)

Berikut dokumen yang sudah tersedia sebagai source-of-truth untuk AI model yang akan melanjutkan:

| Dokumen | Isi | Status |
|---|---|---|
| `AGENTS.md` | Panduan onboarding AI, aturan #1, struktur dokumen | ✅ Lengkap |
| `PROJECT_STATE.md` | Status terkini, blocker, next actions, bug log | ✅ Lengkap |
| `INVARIANTS.md` | Aturan mutlak yang tidak boleh dilanggar | ✅ Lengkap |
| `docs/ARCHITECTURE.md` | Struktur sistem, layer, modul, alur posting | ✅ Lengkap |
| `docs/DATABASE_RELATIONSHIPS.md` | Tabel, FK, indeks, migrasi | ✅ Lengkap |
| `docs/ACCOUNTING_RULES.md` | Matrix input, posting pipeline, COA, reversal | ✅ Lengkap |
| `docs/PAYMENT_PLAN_V33.md` | Kontrak payment plan, workflow, API, rules | ✅ Lengkap |
| `docs/PURCHASING_AP_RULES.md` | Purchasing, AP, 3-way match, outstanding | ✅ Lengkap |
| `docs/INVENTORY_RULES.md` | Inventory, warehouse, stok | ✅ Lengkap |
| `docs/MANUFACTURING_RULES.md` | BOM, MRP, WO, costing | ✅ Lengkap |
| `docs/FILAMENT_UI_RULES.md` | UI Filament, halaman, otorisasi server | ✅ Lengkap |
| `docs/TESTING_PROTOCOL.md` | Protokol test, baseline, perintah | ✅ Lengkap |
| `docs/BUG_LOG.md` | Bug dan blocker dikenal | ✅ Lengkap |
| `docs/GIT_WORKFLOW.md` | Branch, commit konvensi, alur kerja | ✅ Lengkap |
| `docs/SETUP_INSTALLATION.md` | Setup lokal, .env, troubleshooting | ✅ Lengkap |
| `docs/API_CONTRACTS.md` | Endpoint API PPL dan AP | ✅ Lengkap |
| `docs/DIRECTORY_STRUCTURE.md` | Layout project, file di-gitignore | ✅ Lengkap |
| `docs/CHANGE_DECISIONS.md` | Log keputusan arsitektur | ✅ Lengkap |

---

## BLOCKER AKTIF

| # | Blocker | Dampak | Siapa |
|---|---|---|---|
| 1 | Syntax error di `ApAllocationServiceTest.php:30` | Full suite MERAH | Dev |
| 2 | Migration `2026_10_29_120000` belum diterapkan | Attachment reconciliation belum aktif | DBA/Ops |
| 3 | Live browser acceptance belum dijalankan | R1 belum bisa sign-off | QA/Ops |
| 4 | Physical storage/restore belum diuji | Bukti attachment belum terverifikasi | Ops |
| 5 | Production deployment belum dilakukan | Belum go-live | Manajemen |

---

## TASK STATUS (dari Master Plan)

### Foundation (B)
| ID | Task | Status |
|---|---|---|
| B01 | Kontrak UI/jurnal aktual & source map | ✅ IMPLEMENTED |
| B02 | Jurnal/akun/GL/P&L/neraca/FX | 🔄 IN_PROGRESS |
| B03 | Posting adapter + idempotency | 🔄 IN_PROGRESS |
| B04 | Payment Plan parity test | 🔄 IN_PROGRESS |
| B05 | Regression laporan & relasi aset | 🔄 IN_PROGRESS |

### Payment (F)
| ID | Task | Status |
|---|---|---|
| F01 | Sumber saldo ERP & cutoff | ✅ IMPLEMENTED |
| F02 | Cutoff/pending-debit dedup | ✅ IMPLEMENTED |
| F03 | Partial AP by purchaseBillId | ✅ IMPLEMENTED |
| F04 | AP residual manual/legacy/return/credit note | 🔄 IN_PROGRESS |
| F05 | DP/progress/final/retention | ⏸️ DEFERRED |
| F06 | Pajak/fee/multi-account allocation | ⏳ TODO |

### UI (U)
| ID | Task | Status |
|---|---|---|
| U01 | Panel Filament & master divisi | ✅ IMPLEMENTED |
| U02 | Kontrak API v33 lengkap | 🔄 IN_PROGRESS |
| U03 | Lifecycle attachment privat | 🔄 IN_PROGRESS |
| U04 | UI referensi v33 & cetak | ⏳ TODO |
| U05 | Comments & notifications | ⏳ TODO |
| U06 | Dashboard/inbox Filament | 🔄 IN_PROGRESS |

---

## ROADMAP IMPLEMENTASI B01–B12 (dari Audit Lampiran B)

| Fase | ID | Tahapan | Tujuan Utama | Status |
|------|-----|---------|--------------|--------|
| Foundation | B01 | S0 - Discovery dan baseline matriks | Buktikan baseline sebelum modifikasi. Inventaris route, controller, model, migration, policy, service, UI dan test. | ✅ SELESAI |
| Foundation | B02 | S1 - Foundation, master dan menu registry | Satu sumber master/permission. Crosswalk source_system/entity/source_id → target_id. Menu registry tunggal 151 entri. | 🔄 IN_PROGRESS |
| Foundation | B03 | S2 - Posting adapter dan matriks | Semua modul memakai mesin jurnal ERP yang sama. AccountingIntent, idempotency key, journal_links, period lock, reversal. | 🔄 IN_PROGRESS |
| Foundation | B04 | S3 - Payment Plan parity dan adapter | Integrasi domain v33 secara setara. Identity Sites → sesi ERP. Map DB/R2 via adapter. Expected version + financial mutation fence. | 🔄 IN_PROGRESS |
| Foundation | B05 | S4 - Saldo, allocation dan posting payment | Tutup gap finansial v33. Payment account policy effective date. Reservation atomik per account/currency. | 🔄 IN_PROGRESS |
| Operations | B06 | S5A - IPR, PO, GRN, invoice dan AP | Adopsi PO umum/IPR, tracking, MOQ, perubahan, receipt dan retur. Three-way match exception. | ⏳ TODO |
| Operations | B07 | S5B - Inventory ledger dan opname | Satu mutation ledger untuk receipt/issue/return/transfer/opname. Opening stock wizard, stock card, opname freeze/count/recount. | ⏳ TODO |
| Operations | B08 | S6 - BOM, MRP, WO, routing dan costing | Adopsi CBD/BOM, MRP, SPK, preparation, sembilan process template. Routing configurable. | ⏳ TODO |
| Operations | B09 | S7 - Sales, AR, subkontrak, mekanik, scrap | Sales lokal/ekspor, delivery, FG dispatch, invoice, receipt allocation, AR aging. | ⏳ TODO |
| Operations | B10 | S8 - EXIM, reports dan import/export | Realisasikan seluruh jenis BC dan tujuh laporan. Document type+direction canonical. | ⏳ TODO |
| Release | B11 | S9A - UAT, migrasi dan restore rehearsal | Migration runbook reproduktif. Dry run staging dari backup. UAT operasional, UX dan keamanan. | ⏳ TODO |
| Release | B12 | S9B - Paket cutover yang reviewable | Paket final untuk aktivasi. Release notes, migration order, feature flags, smoke commands, rollback decision tree. | ⏳ TODO |

**Prioritas P0 (sebelum transaksi nyata):**
- Inventoris repository Marvel-Dard dan kunci kontrak jurnal matriks melalui regression test
- Satu identitas perusahaan, user, COA, supplier/customer, material dan bank; tidak mengimpor master tanpa crosswalk
- Port adapter identitas dan penyimpanan v33; jangan percaya header Sites pada server umum
- Cegah jurnal ganda, pembayaran ganda, perubahan transaksi posted dan kebocoran lintas perusahaan
- Sahkan mandat rekening: source v33 memakai IDR 3 dan USD 1; rencana lama berbeda

**Prioritas P1 dan P2:**
- P1: purchasing sampai GRN, invoice/AP, Payment Plan, inventory ledger, rekonsiliasi dan laporan
- P2: BOM/MRP, WO/routing produksi, sales/AR, subkontrak, mekanik, scrap, serta dokumen customs

---

## TEST RESULTS (terakhir terverifikasi)
```
Full suite: 721 tests / 14.795 assertions — PASS
Payment Plan bank: 4 tests / 178 assertions — PASS
MySQL integration: 6 tests / 146 assertions / 1 skipped — PASS
PHP lint: No syntax errors — PASS
git diff --check: PASS
```

**CATATAN:** Angka di atas dari rekap R1. Syntax error di `ApAllocationServiceTest.php:30` ditemukan SETELAH rekap tersebut. Perlu re-run setelah fix.

---

## NEXT ACTIONS (prioritas)

1. **FIX** syntax error di `ApAllocationServiceTest.php:30` → BLOCKER
2. **RE-RUN** full suite untuk establish baseline baru
3. **F04-2**: Implementasi ApResidualService + migration
4. **F04-3**: HTTP integration + endpoint tests
5. **F04-4**: MySQL cross-account race tests
6. **PARALEL**: Jalankan acceptance artifacts di staging (ops)

---

## KEPUTUSAN TERAKHIR

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-10-29 | R1 scope = bank IDR saja | USD/petty/DP ditunda |
| 2026-10-29 | Canonical page = `/erp-panel/bank-payments` | Satu UI final |
| 2026-10-29 | Attachment reconciliation context | Bukan hanya request |
| 2026-10-29 | Print multi-item + foto bukti | Requirement Finance |
| 2026-10-30 | F04 = AP residual completion | Natural next step |
| 2026-10-30 | Credit notes via reconciliation | Tidak ada tabel credit_notes |


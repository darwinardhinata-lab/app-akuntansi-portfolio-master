# PROJECT_STATE.md — Status Terkini ERP Marvel-Dard

> Last Updated: 2026-10-30
> Branch: `feat/marvel-dard-integration`
> Commit: `3bbb54e` (R1 acceptance hardening artifacts)

---

## STATUS KESELURUHAN

| Area | Status | Catatan |
|---|---|---|
| R1 Finance Payment UI (bank IDR) | ✅ Code-complete | 721 tests PASS |
| R1 Acceptance Hardening | 🔄 Artifacts dibuat | Belum dijalankan di staging |
| F04 AP Residual | 🔄 IN_PROGRESS | Discovery selesai, coding belum |
| F05 DP/Progress/Retention | ⏸️ DEFERRED | Setelah R1 accepted |
| F06 Pajak/Fee/Multi-account | ⏳ TODO | Setelah F04 |
| R2 Three-way match | ⏳ TODO | Discovery dulu |
| USD Payment | ⏸️ DEFERRED | Setelah R1 |
| Petty Cash | ⏸️ DEFERRED | Setelah R1 |
| Filament Panel | 🔄 IN_PROGRESS | Canonical pages dibuat |
| Manufacturing (MFG) | ✅ SELESAI | Native Laravel port |

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


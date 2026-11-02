# BUG_LOG.md — Daftar Bug & Blocker Dikenal

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Commit: `df0f22b`  
> Status: BINDING — semua AI model WAJIB memeriksa file ini sebelum coding

---

## CARA MENAMBAH ENTRI BARU

Tambahkan baris di bagian atas tabel (terbaru di atas) dengan format:

| Tanggal | ID Bug | Judul | Severity | Status | Penanganan |

---

## DAFTAR BUG & BLOKER AKTIF

| Tanggal | ID | Judul | Severity | Status | Penanganan |
|---------|-----|-------|----------|--------|------------|
| 2026-10-30 | BUG-001 | Syntax error di `ApAllocationServiceTest.php:30` | 🔴 Tinggi | OPEN | Full suite MERAH. Perlu fix dan re-run setelah ini. Ditemukan setelah rekap R1. |
| 2026-10-30 | BUG-002 | Migration `2026_10_29_120000` belum diterapkan | 🟡 Sedang | OPEN | Attachment reconciliation belum aktif. Butuh DBA/Ops untuk apply. |
| 2026-10-30 | BUG-003 | Beberapa migrasi memiliki duplikasi nama (suffix `.phpX`) | 🟡 Sedang | OPEN | File: `...000001_add_three_way_match_to_purchase_invoices.phpX`, `...000002_add_ap_outstanding_tracking_to_purchase_bills.phpX`, `...000003_create_ppl_ap_payment_allocations.phpX`. Perlu dibersihkan sebelum production. |
| 2026-10-30 | BLOCKER-01 | Live browser acceptance belum dijalankan | 🔴 Tinggi | OPEN | R1 belum bisa sign-off. Butuh QA/Ops. |
| 2026-10-30 | BLOCKER-02 | Physical storage/restore belum diuji | 🟡 Sedang | OPEN | Bukti attachment belum terverifikasi. Butuh Ops. |
| 2026-10-30 | BLOCKER-03 | Production deployment belum dilakukan | 🔴 Tinggi | OPEN | Belum go-live. Butuh approval manajemen. |

---

## BUG YANG SUDAH DIPERBAIKI (RESOLVED)

| Tanggal | ID | Judul | Resolusi |
|---------|-----|-------|----------|
| — | — | — | (belum ada entri resolved) |

---

## CATATAN PENTING

- Angka test (721 tests / 14.795 assertions) adalah dari rekap R1. Syntax error BUG-001 ditemukan **setelah** rekap tersebut. Perlu re-run full suite untuk establish baseline baru setelah fix.
- Jika menemukan bug baru, tambahkan entri di atas **SEBELUM** mulai coding fix.
- Setiap bug fix harus diikuti dengan test yang memastikan bug tidak kembali.

_Last Updated: 2026-11-02_
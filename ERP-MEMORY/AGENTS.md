# AGENTS.md — ERP Marvel-Dard: Panduan Onboarding AI

> **Tujuan file ini:** Setiap AI model yang melanjutkan proyek ini harus
> membaca file ini PERTAMA. Waktu baca target: 5 menit.
>
> Last Updated: 2026-10-30
> Branch aktif: `feat/marvel-dard-integration`
> Commit terakhir terverifikasi: `3bbb54e` (atau setelahnya)

---

## IDENTITAS PROYEK

| Aspek | Nilai |
|---|---|
| Nama | ERP Marvel-Dard (PT. Magicase Group Indonesia) |
| Jenis | ERP Akuntansi + Manufaktur + Finance |
| Stack | Laravel 12.61.1, PHP 8.2.12, MySQL, Blade + Filament 5 + Livewire 4 |
| Test | PHPUnit 11.5.55 (721 tests / 14.795 assertions baseline) |
| Repo | `D:\xampp\htdocs\app-akuntansi-portfolio-master` |
| Branch | `feat/marvel-dard-integration` (JANGAN merge ke main tanpa approval) |

---

## ATURAN #1: BACA SEBELUM MELAKUKAN APAPUN

1. **Baca `INVARIANTS.md`** — aturan yang TIDAK BOLEH dilanggar
2. **Baca `PROJECT_STATE.md`** — status terkini, apa yang sudah/belum
3. **Baca `docs/CHANGE_DECISIONS.md`** — keputusan arsitektur dan alasannya
4. **Baca `docs/ACCOUNTING_RULES.md`** — jika menyentuh jurnal/ledger
5. **Baca `docs/PAYMENT_PLAN_V33.md`** — jika menyentuh payment/finance

---

## PRINSIP MUTLAK (ringkasan — detail di INVARIANTS.md)

1. **Satu ledger, satu PostingService** — semua modul mengirim AccountingIntent,
   BUKAN menulis SQL langsung ke tabel journal
2. **Model matriks Marvel-Dard dipertahankan** — jangan ganti dengan general entry
3. **Payment Plan BUKAN buku besar kedua** — hanya workflow otorisasi
4. **Company scope + expected_version + idempotency key** di setiap mutasi finansial
5. **BCMath untuk semua perhitungan finansial** — JANGAN float
6. **Migration additive & backward-compatible** — feature flag default OFF
7. **Discovery SEBELUM coding** — jangan asumsikan struktur tabel/route

---

## STRUKTUR DOKUMEN

| File | Kapan Baca |
|---|---|
| `AGENTS.md` | SELALU — entry point |
| `PROJECT_STATE.md` | SELALU — status terkini |
| `INVARIANTS.md` | SELALU — aturan mutlak |
| `docs/ARCHITECTURE.md` | Saat menyentuh struktur sistem |
| `docs/DATABASE_RELATIONSHIPS.md` | Saat menyentuh tabel/migrasi |
| `docs/ACCOUNTING_RULES.md` | Saat menyentuh jurnal/posting |
| `docs/PAYMENT_PLAN_V33.md` | Saat menyentuh payment/finance |
| `docs/PURCHASING_AP_RULES.md` | Saat menyentuh purchasing/AP |
| `docs/INVENTORY_RULES.md` | Saat menyentuh inventory/warehouse |
| `docs/MANUFACTURING_RULES.md` | Saat menyentuh BOM/MRP/WO |
| `docs/FILAMENT_UI_RULES.md` | Saat menyentuh UI Filament |
| `docs/TESTING_PROTOCOL.md` | Saat menulis test |
| `docs/CHANGE_DECISIONS.md` | Sebelum membuat keputusan baru |

---

## KONVENSI PENTING

### Penamaan
- Tabel: `snake_case`, prefix modul (`mfg_`, `ppl_`, dll)
- Model: `PascalCase`, singular
- Route: `kebab-case`, prefix modul
- Test: `test_<apa_yang_diuji>` dalam Bahasa Inggris
- Commit: `feat(scope): deskripsi` atau `fix(scope): deskripsi`

### Uang & Finansial
- Semua kolom uang: `decimal(20,2)`
- Semua perhitungan: BCMath (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`)
- JANGAN pernah: `+`, `-`, `*`, `/` pada string jumlah
- JANGAN pernah: `number_format()` sebelum save ke DB

### Jurnal
- Semua posting via `PostingService` — JANGAN direct SQL
- `journal_headers.evidence_number` = nomor dokumen sumber
- Jurnal POSTED tidak boleh dihapus — koreksi via reversal
- `accounting_posting_intents` = idempotency + audit trail

### Security
- Company scope di SETIAP query finansial
- `expected_version` fence di setiap mutation
- Idempotency key unik per mutasi finansial
- Hidden button BUKAN otorisasi — server tetap penentu

---

## CARA MENJALANKAN TEST

```powershell
# Full suite
php vendor/phpunit/phpunit/phpunit --no-progress

# Payment Plan specific
php vendor/phpunit/phpunit/phpunit tests/Feature/PaymentPlanBankHttpTest.php --no-progress

# MySQL integration (opt-in)
$env:AP_MYSQL_TESTS='1'
php vendor/phpunit/phpunit/phpunit tests/Integration/PaymentPlanMakerMysqlTest.php --no-progress

# PHP lint
php -l <file>

# Git diff check
git diff --check
```

---

## JIKA RAGU

1. **STOP** — jangan tebak
2. **Discovery dulu** — baca source aktual
3. **Laporkan** — jelaskan apa yang ditemukan
4. **Tunggu konfirmasi** — sebelum menulis patch

---

## KONTAK & APPROVAL

Keputusan yang membutuhkan manusia:
- Merge ke main
- Deploy production
- Migration ke database operasional
- Perubahan COA/mandat rekening
- Aktivasi feature flag production
- Transfer bank nyata

---

# AGENTS.md — ERP Marvel-Dard: Panduan Onboarding AI

> **Tujuan file ini:** Setiap AI model yang melanjutkan proyek ini **WAJIB** membaca
> file ini PERTAMA. Waktu baca target: 10 menit. Jangan menebak — baca semua file
> referensi yang disebutkan di bawah sebelum menulis kode.
>
> Last Updated: 2026-11-02
> Branch aktif: `feat/marvel-dard-integration`
> Commit terakhir terverifikasi: `df0f22b` (HEAD terbaru, synced dengan origin)
> Repo: `D:\xampp\htdocs\app-akuntansi-portfolio-master`
> Dokumen sumber rencana: `d:/Project ERP/Prompt_Cline_Integrasi_Filament_Livewire_20261027.txt` dan `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt`

---

## IDENTITAS PROYEK

| Aspek | Nilai |
|---|---|
| Nama | ERP Marvel-Dard (PT. Magicase Group Indonesia) |
| Jenis | ERP Akuntansi + Manufaktur + Finance (monolit Laravel) |
| Stack | Laravel 12.61.1, PHP 8.2.12, MySQL 8.x, Blade + Filament 5 + Livewire 4 |
| Test Runner | PHPUnit 11.5.55 |
| Baseline Test | 721 tests / 14.795 assertions (R1) |
| Root Folder | `D:\xampp\htdocs\app-akuntansi-portfolio-master` |
| Branch | `feat/marvel-dard-integration` (JANGAN merge ke main tanpa approval) |
| Remote | `https://github.com/darwinardhinata-lab/app-akuntansi-portfolio-master.git` |

---

## ATURAN #1: BACA SEBELUM MELAKUKAN APAPUN

1. **Baca `INVARIANTS.md`** — aturan yang TIDAK BOLEH dilanggar
2. **Baca `PROJECT_STATE.md`** — status terkini, bloker, next actions, bug log
3. **Baca `docs/CHANGE_DECISIONS.md`** — keputusan arsitektur dan alasannya
4. **Baca `docs/ARCHITECTURE.md`** — struktur sistem, layer, modul
5. **Baca `docs/DATABASE_RELATIONSHIPS.md`** — tabel, FK, indeks, migrasi
6. **Baca `docs/ACCOUNTING_RULES.md`** — jika menyentuh jurnal/ledger
7. **Baca `docs/PAYMENT_PLAN_V33.md`** — jika menyentuh payment/finance
8. **Baca `docs/PURCHASING_AP_RULES.md`** — jika menyentuh purchasing/AP
9. **Baca `docs/INVENTORY_RULES.md`** — jika menyentuh inventory/warehouse
10. **Baca `docs/MANUFACTURING_RULES.md`** — jika menyentuh BOM/MRP/WO
11. **Baca `docs/FILAMENT_UI_RULES.md`** — jika menyentuh UI Filament
12. **Baca `docs/TESTING_PROTOCOL.md`** — sebelum menulis atau menjalankan test
13. **Baca `docs/BUG_LOG.md`** — bug dan blocker yang sudah dikenal
14. **Baca `docs/GIT_WORKFLOW.md`** — branch, commit, push, PR
15. **Baca `docs/SETUP_INSTALLATION.md`** — cara menjalankan project lokal
16. **Baca `docs/IMPLEMENTATION_ROADMAP.md`** — tahap B01–B12 yang sedang/akan dikerjakan
17. **Baca `docs/FILAMENT_LIVEWIRE_ADOPTION.md`** — jika menyentuh UI Filament/Livewire
18. **Baca `docs/MENU_MAP.md`** — jika menyentuh menu/navigasi
19. **Baca `docs/MASTER_DATA_CROSSWALK.md`** — jika menyentuh master data (COA, party, material, bank)
20. **Baca `docs/CUTOVER_ROLLBACK.md`** — sebelum UAT, migrasi, cutover, atau rollback
21. **Baca `docs/TEST_EVIDENCE.md`** — sebelum klaim acceptance

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
8. **Server-side authorization** — hidden button bukan otorisasi
9. **Private attachment** — SHA-256 hash server-side, download via authorized endpoint
10. **Audit trail append-only** — tidak boleh dihapus

---

## STRUKTUR DOKUMEN

| File | Kapan Baca | Status |
|---|---|---|
| `AGENTS.md` | SELALU — entry point | ✅ Lengkap |
| `PROJECT_STATE.md` | SELALU — status terkini + bug log | ✅ Lengkap |
| `INVARIANTS.md` | SELALU — aturan mutlak binding | ✅ Lengkap |
| `docs/ARCHITECTURE.md` | Struktur sistem, layer, modul | ✅ Lengkap |
| `docs/DATABASE_RELATIONSHIPS.md` | Tabel, FK, indeks, migrasi | ✅ Lengkap |
| `docs/ACCOUNTING_RULES.md` | Jurnal, posting, COA, period lock | ✅ Lengkap |
| `docs/PAYMENT_PLAN_V33.md` | Kontrak payment plan, workflow, API | ✅ Lengkap |
| `docs/PURCHASING_AP_RULES.md` | Purchasing, AP, 3-way match | ✅ Lengkap |
| `docs/INVENTORY_RULES.md` | Inventory, warehouse, stok | ✅ Lengkap |
| `docs/MANUFACTURING_RULES.md` | BOM, MRP, WO, costing | ✅ Lengkap |
| `docs/FILAMENT_UI_RULES.md` | UI Filament, otorisasi server | ✅ Lengkap |
| `docs/TESTING_PROTOCOL.md` | Menulis & menjalankan test | ✅ Lengkap |
| `docs/BUG_LOG.md` | Bug dan blocker dikenal | ✅ Lengkap |
| `docs/GIT_WORKFLOW.md` | Branch, commit, push, PR | ✅ Lengkap |
| `docs/SETUP_INSTALLATION.md` | Setup lokal, .env, troubleshooting | ✅ Lengkap |
| `docs/API_CONTRACTS.md` | Endpoint API PPL dan AP | ✅ Lengkap |
| `docs/DIRECTORY_STRUCTURE.md` | Layout project, file di-gitignore | ✅ Lengkap |
| `docs/CHANGE_DECISIONS.md` | Sebelum membuat keputusan baru | ✅ Lengkap |
| `docs/IMPLEMENTATION_ROADMAP.md` | Roadmap implementasi B01–B12 (dari audit Lampiran B) | ✅ Lengkap |
| `docs/MENU_MAP.md` | Pemetaan 151 menu Addonan ke target ERP (dari audit Lampiran A) | ✅ Lengkap |
| `docs/MASTER_DATA_CROSSWALK.md` | Crosswalk master data lintas sistem | ✅ Lengkap |
| `docs/FILAMENT_LIVEWIRE_ADOPTION.md` | Panduan adopsi Filament + Livewire (dari Prompt Cline 27 Okt 2026) | ✅ Lengkap |
| `docs/CUTOVER_ROLLBACK.md` | UAT, migrasi, cutover dan rollback (dari audit bab 21–24) | ✅ Lengkap |
| `docs/TEST_EVIDENCE.md` | Bukti pengujian dan acceptance criteria | ✅ Lengkap |
| `docs/SALES_AR_RULES.md` | Aturan Sales, AR, penerimaan kas/bank | ✅ Lengkap |
| `docs/EXIM_CUSTOMS_RULES.md` | Aturan EXIM, BC, dokumen pabean | ✅ Lengkap |

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

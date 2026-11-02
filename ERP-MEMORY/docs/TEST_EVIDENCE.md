# TEST_EVIDENCE.md — Bukti Pengujian dan Acceptance Criteria

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: Audit Lampiran C dan Prompt Cline Filament/Livewire 27 Okt 2026

---

## 1. BASELINE TEST (R1)

| Suite | Tests | Assertions | Status | Catatan |
|-------|-------|------------|--------|---------|
| Full suite | 721 | 14.795 | PASS (rekap R1) | Syntax error BUG-001 ditemukan setelah rekap |
| Payment Plan bank | 4 | 178 | PASS | |
| MySQL integration | 6 | 146 | PASS (1 skipped) | Butuh `AP_MYSQL_TESTS=1` |

**Catatan:** Syntax error di `ApAllocationServiceTest.php:30` (BUG-001) ditemukan setelah rekap R1. Full suite perlu di-re-run setelah fix.

---

## 2. PERINTAH TEST

```powershell
# Full suite (semua test)
php vendor/phpunit/phpunit/phpunit --no-progress

# Hanya Payment Plan
php vendor/phpunit/phpunit/phpunit tests/Feature/PaymentPlanBankHttpTest.php --no-progress

# Hanya ApAllocation
php vendor/phpunit/phpunit/phpunit tests/Feature/ApAllocation/ --no-progress

# Integration (butuh MySQL)
$env:AP_MYSQL_TESTS='1'
php vendor/phpunit/phpunit/phpunit tests/Integration/PaymentPlanMakerMysqlTest.php --no-progress

# Lint satu file PHP
php -l <file>
```

---

## 3. ATURAN MENULIS TEST

1. Nama test file: `test_<apa_yang_diuji>` dalam Bahasa Inggris, di dalam folder `tests/Feature/` atau `tests/Unit/`
2. Setiap test harus dapat dijalankan dan hasilnya harus dicatat: environment, perintah, expected, actual, tanggal
3. Jangan klaim PASS tanpa menjalankan test (INV-26)
4. Jika test tidak bisa dijalankan, tulis `NOT RUN` + alasan di catatan
5. Setiap bug fix harus diikuti test regresi untuk mencegah bug kembali
6. Test finansial harus menggunakan BCMath, bukan float
7. Test harus mencakup company scope (setiap query finansial filter `company_id`)

---

## 4. ACCEPTANCE CRITERIA (WAJIB DIBUKTIKAN)

| No | Kriteria | Bukti yang Diperlukan | Status |
|----|----------|----------------------|--------|
| 1 | Dependency resolver, composer validate, build asset dan PHP syntax benar | Hasil `composer validate`, `php -l`, build output | |
| 2 | Lockfile version terpilih dicatat, bukan diasumsikan | Catatan versi di dokumen ini | |
| 3 | Panel off menutup akses termasuk reactive actions | Test panel off/on | |
| 4 | Panel on dengan session ERP, active member dan permission sesuai role | Test otorisasi | |
| 5 | ADMIN tidak otomatis mendapat hak pembayaran | Test role-based access | |
| 6 | Unauthorized mount/action ditolak | Feature/Livewire tests | |
| 7 | ID tampering ditolak | Test manipulasi ID | |
| 8 | Company asing ditolak | Test company scope | |
| 9 | Role dicabut setelah mount ditangani | Test session expiry | |
| 10 | Stale version ditangani | Test expected_version fence | |
| 11 | Retry tidak menggandakan keputusan | Test idempotency | |
| 12 | Relation lookup tidak bocor data | Test query scope | |
| 13 | Export tidak bocor data | Test export authorization | |
| 14 | Satu master irisan lengkap memanggil service dan audit yang benar | Test master CRUD | |
| 15 | Posted data tidak dapat diubah lewat Resource/bulk/inline mutation | Test immutability | |
| 16 | Dashboard stats sama dengan query sumber/drill-down | Test dashboard query | |
| 17 | Dashboard membedakan currency, route, historical dan transaksi aktual | Test dashboard filter | |
| 18 | Golden PHP/Node 66 tetap lulus termasuk full projection | Test hasil golden | |
| 19 | Hardened tests terpisah lulus | Test hardened | |
| 20 | Service/HTTP nyata lulus | Test integrasi | |
| 21 | Minimal 1 HTTP test lama bukan bukti seluruh endpoint aman | Catatan cakupan test | |
| 22 | Browser desktop/mobile 360/390/768: panel, filter/modal, keyboard/fokus | Hasil uji browser | |
| 23 | Login/expiry, layout Bootstrap lama, v33 Blade, attachment | Hasil uji browser | |
| 24 | Multi-page print, tiga bahasa | Hasil uji browser | |
| 25 | Tidak Alpine ganda atau duplicate submission | Hasil uji browser | |
| 26 | MySQL dua proses/transaksi untuk reservation dana/qty/AP | Test integrasi MySQL | |
| 27 | Duplicate post ditangani | Test idempotency | |
| 28 | Deadlock/retry diuji | Test MySQL concurrency | |
| 29 | SQLite tidak menggantikan MySQL untuk acceptance | Catatan environment | |
| 30 | Rekonsiliasi AP/AR/stock/cash/GL diuji | Laporan rekonsiliasi | |
| 31 | Restore DB/private objects staging diuji | Hasil restore staging | |
| 32 | Jalankan tests relevan per patch | Catatan per patch | |
| 33 | Full suite setelah perubahan dependency/core dan sebelum laporan gabungan | Hasil full suite | |

---

## 5. TEST BUG LOG (BERDASARKAN BUG_LOG.md)

| ID | Judul | Severity | Status | Test Regresi |
|----|-------|----------|--------|--------------|
| BUG-001 | Syntax error di `ApAllocationServiceTest.php:30` | Tinggi | OPEN | Belum ada |
| BUG-002 | Migration `2026_10_29_120000` belum diterapkan | Sedang | OPEN | Belum ada |
| BUG-003 | Duplikasi nama migrasi (suffix `.phpX`) | Sedang | OPEN | Belum ada |
| BLOCKER-01 | Live browser acceptance belum dijalankan | Tinggi | OPEN | Belum ada |
| BLOCKER-02 | Physical storage/restore belum diuji | Sedang | OPEN | Belum ada |
| BLOCKER-03 | Production deployment belum dilakukan | Tinggi | OPEN | Belum ada |

---

## 6. CARA MENCATAT HASIL TEST

Setiap test yang dijalankan harus dicatatan dengan format:

| Tanggal | Test | Perintah | Environment | Expected | Actual | Status | Catatan |
|---------|------|----------|-------------|----------|--------|--------|---------|

**Jangan menyatakan test lulus jika tidak dijalankan.**

---

## 7. TEST PER TAHAPAN B01–B12

| Tahapan | Test yang Diperlukan | Bukti | Status |
|---------|---------------------|-------|--------|
| B01 | Baseline matriks regression | baseline-map.md, matrix-contract.md | ✅ |
| B02 | Route contracts, unique constraints, authorization | menu-map, crosswalk | 🔄 |
| B03 | Concurrent duplicate posting, reversal, period lock | PostingService tests | 🔄 |
| B04 | State machine transitions, company scope | Payment Plan tests | 🔄 |
| B05 | Reservation atomik, allocation AP/DP/progress/final/retention | Payment allocation tests | 🔄 |
| B06 | Three-way match, partial receipt, over-receipt | Purchasing/AP tests | ⏳ |
| B07 | Negative stock prevention, transfer consistency | Inventory tests | ⏳ |
| B08 | Material conservation, rework no double count | Manufacturing tests | ⏳ |
| B09 | Partial dispatch, receipt allocation, AR aging | Sales/AR tests | ⏳ |
| B10 | BC context, report drill-down, import quarantine | EXIM/report tests | ⏳ |
| B11 | UAT operational, UX, security | UAT sign-off | ⏳ |
| B12 | Cutover checklist, rollback decision tree | Release notes | ⏳ |
# TESTING_PROTOCOL.md — Protokol Testing ERP Marvel-Dard

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Test Runner: PHPUnit 11.5.55

---

## TIPE TEST

| Tipe | Lokasi | Tujuan | Kapan Dijalankan |
|---|---|---|---|
| Unit | `tests/Unit/` | Uji unit terisolasi (model, service, helper) | Setiap perubahan |
| Feature | `tests/Feature/` | Uji fitur end-to-end (HTTP, service flow) | Setiap perubahan fitur |
| Integration | `tests/Integration/` | Uji integrasi MySQL (butuh DB nyata) | Sebelum release, opt-in |

---

## PERINTAH TEST

```powershell
# Full suite (semua test)
php vendor/phpunit/phpunit/phpunit --no-progress

# Hanya Payment Plan
php vendor/phpunit/phpunit/phpunit tests/Feature/PaymentPlanBankHttpTest.php --no-progress

# Hanya ApAllocation
php vendor/phpunit/phpunit/phpunit tests/Feature/ApAllocation/ --no-progress

# Integration (butuh $env:AP_MYSQL_TESTS='1')
$env:AP_MYSQL_TESTS='1'
php vendor/phpunit/phpunit/phpunit tests/Integration/PaymentPlanMakerMysqlTest.php --no-progress

# Lint satu file PHP
php -l <file>
```

---

## BASELINE TEST (R1)

| Suite | Tests | Assertions | Status |
|---|---|---|---|
| Full suite | 721 | 14.795 | PASS (rekap R1) |
| Payment Plan bank | 4 | 178 | PASS |
| MySQL integration | 6 | 146 | PASS (1 skipped) |

> ⚠️ Catatan: Syntax error di `ApAllocationServiceTest.php:30` (BUG-001) ditemukan setelah rekap R1. Full suite perlu di-re-run setelah fix.

---

## ATURAN MENULIS TEST

1. **Nama test file**: `test_<apa_yang_diuji>` dalam Bahasa Inggris, di dalam folder `tests/Feature/` atau `tests/Unit/`
2. **Setiap test harus dapat dijalankan** dan hasilnya harus dicatat: environment, perintah, expected, actual, tanggal
3. **Jangan klaim PASS tanpa menjalankan test** (INV-26)
4. **Jika test tidak bisa dijalankan**, tulis `NOT RUN` + alasan di catatan
5. **Setiap bug fix harus diikuti test regresi** untuk mencegah bug kembali
6. **Test finansial harus menggunakan BCMath**, bukan float
7. **Test harus mencakup company scope** (setiap query finansial filter `company_id`)

---

## SEBELUM MENGERJAKAN PATCH

1. Baca `BUG_LOG.md` — apakah ada bug terkait yang sudah dikenal?
2. Baca `docs/CHANGE_DECISIONS.md` — apakah keputusan terkait sudah ada?
3. Jalankan test suite terkait untuk establish baseline sebelum berubah
4. Tulis test baru untuk fitur/perbaikan yang akan dibuat
5. Jalankan test setelah perubahan, pastikan semua PASS
6. Commit dan catat keputusan jika mengubah arsitektur

_Last Updated: 2026-11-02_
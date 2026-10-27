# ERP balance and partial AP extension

- New source-backed GL projection proves before-post, journal-without-ACK and after-ACK totals equal; opening/receipts and actual payment not doubled. Synthetic persisted crash state, not real process kill.
- AP service test verifies residual cap and AP debit without expense; real HTTP data-provider runs direct and partial AP payment through private evidence to actual PostingService journal. AP fixture opening 500.00 / invoice 200.50 / payment 100.25 / bank 399.75 / residual 100.25.
- Target **5 tests / 88 assertions**, pass. First full **719 tests / 14,562 assertions, 1 failure**: existing source guard rejected raw journal read. Replaced reads with ProtectedJournalQuery; final full **719 tests / 14,646 assertions**, exit 0. Oracle **66/66**.
- Initial AP fixture schema error transaction_date/contact_name corrected to actual bill_date/vendor_name. No operational migration. MySQL races, DP, UI-v33/browser/physical storage and restore still unverified.

# Functional direct-IDR bank path

- New service and actual HTTP tests execute request, two Director approvals, settlement, maker reservation, GM, private evidence, confirmed payment event and real PostingService journal. Service test ACK retry yields same journal, reservation overspend/unsafe return denied. HTTP mapping failure preserves paid and version; approved test mapping retry posts balanced 100.25/100.25 once.
- First workbench HTTP render failed complex inline @json parse; controller now passes endpoint array, repeated test green.
- Target bank/workflow **4 tests / 73 assertions**, pass. Final full isolated SQLite suite **716 tests / 14,597 assertions**, exit 0. Oracle **66/66**, syntax/diff pass.
- Scope and exclusions in bank-flow-development.md. Storage::fake/HTTP kernel not physical staging/browser acceptance; no live DB migration or money movement.

# Targeted security updates and real reactive request test

- Native Composer JSON before/after saved; 34 unique IDs triaged with usage and prod/dev scope, selected patched versions in composer-advisory-triage.csv.
- Nine affected packages + guzzle promises patched after targeted minimal-change dry-run. Laravel 12.70.1; Composer audit **0**, exit 0; validate/platform pass. No global update/bypass.
- Existing frontend `npm install --ignore-scripts` then explicit Vite build **exit 0**; npm audit **0**, exit 0. package-lock.json generated.
- ErpPanelReactiveHttpTest **1 test / 10 assertions**, exit 0, signed actual panel snapshot and real update endpoint, permission/company/session/flag/upload guards; missing-CSRF **419** with middleware test bypass explicitly disabled.
- Final full configured isolated SQLite suite **714 tests / 14,553 assertions**, exit 0. Node legacy oracle **66/66**. No visual browser or MySQL/restore evidence inferred.

# Legacy company Sync to Temp retirement

- Six UI modules cleaned: journal, SO, invoice, PO, purchase bill, product. All legacy sync buttons/forms/JS removed, including Sync Dashboard aliases. Six HTTP dispatch routes removed; temp data/jobs retained.
- Regression confirms named routes absent, POST unavailable even with stale legacy flag=true and no queued dispatch; all six changed Blade templates compile.
- Initial test failure was 405 vs expected 404 because existing GET /{id} routes match obsolete text; corrected allowed unavailable statuses to 404/405, retained no-dispatch assertion.
- Targeted legacy-sync/invoice group **8 tests / 92 assertions**, exit 0. Full configured suite **713 tests / 14,543 assertions**, exit 0, SQLite :memory:. Syntax and whitespace checks pass.
- No user DB writes/migrations, cache clearing or temp-table deletion performed.

# Sales invoice legacy schema regression

- User reported MySQL GET /sales-invoices 500: pivot referenced si.cancelled_at on older operational schema. No user cookies or session values retained here.
- Reproduced on isolated SQLite :memory: by reversing only cancellation-audit migration in test: SalesInvoiceIndexTest **4 tests / 21 assertions, 1 failure**, exact missing si.cancelled_at cause.
- Controller now checks physical column existence before adding cancellation exclusion. Updated schema still excludes cancelled invoices from pivot; audit list retains originals. No cancellation mutation/service behavior changed and no operational migration run.
- Regression covers real HTTP index on legacy schema, nonzero decimal totals, plus updated-schema cancellation exclusion. Existing monthly/date-filter tests preserved.
- Targeted sales invoice/audit group **12 tests / 57 assertions**, exit 0. Full configured suite **711 tests / 14,530 assertions**, exit 0; PHP syntax and diff checks pass.
- Original MySQL browser request not replayed (no session-cookie reuse or operational DB access). Refresh through existing authenticated browser to confirm live page. Actual schema migration readiness remains a separate controlled staging task.

# Filament/Livewire dependency and first Resource verification

- Targeted Composer dry-run/install: Filament 5.11.0 / Livewire 4.4.8; 35 additions, no existing-version changes. Composer validate/platform requirements pass; package discovery in testing SQLite and filament assets publication pass.
- Composer audit: **34 advisories / 9 existing packages**, exit 1; unresolved release blocker, not pass.
- Early test failures: missing permissions.resource fixture, then sessionless Livewire unit-test mount; corrected required fixture and harness session only. No runtime membership relaxation.
- Panel/Livewire group **2 tests / 21 assertions**, exit 0. Full latest suite **709 tests / 14,522 assertions**, exit 0 on isolated SQLite. Reference Node oracle **66/66**, exit 0.
- No NPM asset build, browser/mobile/print, real reactive HTTP CSRF/session replay, MySQL race or restore acceptance claimed. Published Filament package assets are not a substitute for visual acceptance.

# Continuation — complete golden actions and restricted workflow API

- Actual Node reference: **66/66** (60 transitions + 3 weekly + 3 financial), explicit clock/allRequests and annotated G042/G050 synthetic competitors. Reference full-success projections persisted for PHP comparison.
- PHP pure-domain group: **64 tests / 408 assertions**, exit 0. Legacy parity is distinct from hardened decimal input rules and future reservations.
- Identity/store group: **67 tests / 425 assertions**, exit 0 before HTTP additions.
- First full suite with HTTP nested validation: **707 tests / 14,469 assertions, 1 failure**, empty validated input produced undefined-key 500. Root fixed in controller with validated-input empty default, no guard removed.
- Re-run targeted real route: **1 test / 22 assertions**, exit 0. Final full configured suite: **707 tests / 14,489 assertions**, exit 0, PHP 8.2.12 / PHPUnit 11.5.55 / SQLite :memory: with config-cache safety check.
- Syntax checks of PaymentPlan PHP module and migration/routes passed; diff whitespace check passed. Route is default-off, web auth+CSRF stack; standard HTTP tests do not prove live CSRF enforcement because Laravel disables it during testing. Dedicated CSRF/browser acceptance still needed.
- No MySQL concurrency, attachment storage, end-to-end paid-to-GL, live data reconciliation or restore claimed.

# Resumption — decision transitions and weekly cash projection

- Actual PHP golden parity subset: **22 tests / 122 assertions**, exit 0; Node reference oracle using identical annotated context: **22/22**, exit 0.
- G042 context explicitly includes synthetic competing request with journal reference J1. No original expected result changed. G050 and full 60-transition parity remain unverified.
- Pure v33 group including settlement and weekly projection: **26 tests / 161 assertions**, exit 0.
- Full configured suite: `php vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result`, SQLite `:memory:`, APP_ENV=testing, blank DB_URL, array cache/session, sync queue, config cache absent: **665 tests / 14,199 assertions**, exit 0.
- Syntax checks for changed PHP/Node code and `git diff --check` passed. No API/database/concurrency/UI/restore acceptance inferred from pure-domain tests.

# Combined method / pure PHP v33 approval and financial slice

- Located actual design ZIP `D:\Project ERP\ERP AddOn-ERP Marv-Dard Merged-Claude.zip`; preserved original outside application. Derived JSON records source SHA-256, explicit frozen clock, scope and allRequests for approval.
- Reference approval oracle: **8/8 passed** on actual domain.mjs with frozen Date; PHP approval parity **8 tests / 34 assertions passed**.
- Approval + exact settlement group: **10 tests / 60 assertions passed**. Hardened decimal input checks are separate from legacy golden cases.
- Final configured Laravel suite, SQLite in-memory, no config cache: **649 tests / 14,097 assertions, exit 0**.
- PHP syntax, Node script syntax and git diff whitespace checks passed. UI audit generated 49 source references; none accepted as adapted.
- Full 66-case reference parity, complete PHP state machine, API/DB/concurrency parity, browser, restore and activation are NOT claimed.

# Continuation — shared accounting intents and linked manual reversal

- Command: `php vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result` with APP_ENV=testing, SQLite `:memory:`, blank DB_URL, array cache/session, sync queue, and configuration-cache absence checked.
- Actual final result: **639 tests, 14,034 assertions, exit 0**.
- Added `AccountingPostingIntentTest`: nine tests of base/FX retry, payload conflict, immutable protected writes, transactional failure, flag default, manual reversal, authorization, snapshot preservation and rollback retention.
- Earlier increment in the same continuation: 637 tests / 14,029 assertions. Final count adds two rollback/conflict tests and actor evidence validation.
- Menu inventory validated for exactly 151 ordered IDs, allowed status vocabulary and required route/implementation/permission/test columns for any verified claim. No rows promoted to verified.
- No production migration, real MySQL concurrency, browser acceptance, staging restore or Laravel v33 parity execution claimed.

# Bukti test B01 — 2026-10-26

Working directory: `D:\xampp\htdocs\app-akuntansi-portfolio-master`.
PHP 8.2.12 / PHPUnit 11.5.55; SQLite in-memory. Tidak menguji MySQL lokal, browser, v33 Node, build penuh atau deployment.

## Hasil aktual

| Run | Hasil |
|---|---|
| Unit baseline sebelum fixture baru | 50 tests / 145 assertions, exit 0 |
| Feature terpilih sebelum fixture baru | 60 tests / 801 assertions, exit 0; bukan full suite |
| Fixture baru percobaan pertama | 4 errors, exit 2: helper_codes.normal_balance wajib; fixture diperbaiki, bukan kode produksi |
| Fixture baru setelah perbaikan | 4 tests / 45 assertions, exit 0 |
| Full suite | 625 tests / 13864 assertions, 11 errors + 1 failure, exit 2 |
| Reproduksi existing failures, tanpa test baru | 45 tests / 254 assertions, 11 errors + 1 failure, exit 2 |
| Targeted gate terakhir (filter di bawah) | 57 tests / 421 assertions, exit 0 |

Hasil historis B01 sebelum B01.1: sebelas error berasal dari ExchangeRateTest (5) dan PostingFxTest (6): `Akun 111001 tidak ditemukan untuk pemeriksaan mata uang`, melalui AccountCurrencyGuard/ProtectedJournalQuery. Satu failure AccountImportAuditTest::test_export_xlsx_round_trip_preserves_master_values_in_every_locale: expected KODE AKUN, actual ACCOUNT CODE. Reproduksi tanpa test baru membuktikan kegagalan tidak memerlukan test baru. Hasil sesudah diagnosis/perbaikan tercatat di bawah.

## B01.1 — akar penyebab dan hasil setelah perbaikan

Branch kerja: `fix/b01-1-baseline-regressions`, baseline HEAD `a8f9fe5`. Patch hanya tiga file test; tidak mengubah runtime, guard, schema, seeder produksi atau konfigurasi.

### Currency

CurrencySeeder hanya menyediakan master ISO, bukan accounts. Kedua kelas test menggunakan akun 111001/211001 tanpa membuat account rows. AccountCurrencyGuard membaca accounts langsung melalui koneksi writer (tanpa company scope), sehingga penolakan akun tidak ditemukan benar. Riwayat commit `5d9e4cc` menambahkan guard tersebut tanpa memperbarui fixture posting lama. Account 111001 pada COA MGI adalah kas IDR, bukan akun USD yang boleh direlabel demi test.

Perbaikan: fixture eksplisit FX-RECEIVABLE/FX-PAYABLE (piutang/utang USD), FX-ROUNDING (beban pembulatan base currency). Semua posting test terkait memakai akun skenario tersebut. Tidak menambahkan akun dummy ke runtime. Test tambahan membuktikan FX-MISSING tetap ditolak, header dan detail FX di-rollback. Existing AccountCurrencyGuardTest tetap menguji penolakan currency tidak cocok dan kas/bank base currency.

### Locale

Test gagal juga ketika dijalankan sendiri: bukan memerlukan locale bocor dari test sebelumnya. Loop sengaja memilih id/en/zh_CN tetapi selalu meminta header Indonesia. AccountExport::headings() di HEAD a8f9fe5 menggunakan terjemahan locale aktif; map() mempertahankan nilai master baku. RULES.md bagian 10 mensyaratkan header ekspor memakai terjemahan. Tidak perlu hardcode locale runtime atau mengganti fallback.

Perbaikan test menetapkan locale dan expected header eksplisit per bahasa, memulihkan locale pada finally (termasuk jika pembuatan workbook gagal), memeriksa literal formula-like text/leading zero dan enum master baku, lalu mengimpor ulang dan membandingkan seluruh atribut master.

| Validasi sesudah patch | Hasil |
|---|---|
| ExchangeRateTest master first-use individual | 1 test / 14 assertions, exit 0 |
| PostingFxTest wrapper individual | 1 test / 1 assertion, exit 0 |
| AccountImportAuditTest round-trip individual | 1 test / 45 assertions, exit 0 |
| ExchangeRateTest quoted master individual | 1 test / 5 assertions, exit 0 |
| ExchangeRateTest + PostingFxTest + AccountImportAuditTest | 46 tests / 344 assertions, exit 0 |
| Filter Currency | 85 tests / 580 assertions, exit 0 |
| Tiga kelas terkait + AccountCurrencyGuardTest, random seed 2601 | 52 tests / 364 assertions, exit 0 |
| Full configured suite | 626 tests / 13954 assertions, exit 0 |

Dibanding B01: +1 test, +90 assertions yang tercapai, 11 errors dan 1 failure menjadi nol. Tambahan assertion mencakup assertion lama yang sebelumnya tidak tercapai serta pemeriksaan baru; bukan 90 assertion baru seluruhnya.

B01.1 selesai pada SQLite. B01 keseluruhan **belum lulus**: B01.2 coverage perilaku sekarang dan B01.3 restore staging DB+attachment/control totals masih belum selesai. Tidak mengklaim keselamatan concurrency MySQL, restore, ataupun kesiapan aktivasi integrasi.

## Reproduksi aman (PowerShell)

Jalankan di root absolut di atas, pastikan tidak ada config cache dan jangan menunjuk DB operasional:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:QUEUE_CONNECTION='sync'
$env:PLATFORM_LEGACY_SYNC_ENABLED='false'
if (Test-Path 'bootstrap/cache/config.php') { throw 'Config cache exists' }
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --no-progress --do-not-cache-result --filter IntegrationMatrixBaselineTest
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --no-progress --do-not-cache-result
php vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'ExchangeRateTest|PostingFxTest|AccountImportAuditTest'
php vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'IntegrationMatrixBaselineTest|JournalBalanceTest|ManualJournalCurrencyPrecisionTest|GrnReceivingTest|AccountingPeriodLifecycleTest|LedgerPageTotalsTest|FxBaselineSnapshotTest|PostingServiceDescriptionTest'
```

No config-cache clearing, migration against operational DB, restore or production write was executed. SQLite does not establish MySQL concurrency safety. Test configuration excludes opt-in Integration tests; full suite here means configured PHPUnit suites, not all deployment checks.
# Kontrak aktual matriks — B01

Root: `D:\xampp\htdocs\app-akuntansi-portfolio-master`.

## Input yang dipertahankan

Source utama: `D:\xampp\htdocs\app-akuntansi-portfolio-master\resources\views\journal\create.blade.php` dan `D:\xampp\htdocs\app-akuntansi-portfolio-master\app\Http\Controllers\JournalController.php::store`.

- Header: transaction_date, source_doc_no, description; description disimpan sebagai notes.
- Urutan kolom detail: akun, kode bantu, posisi, nominal, aksi hapus. Detail dinamis, bukan layout 12 blok. Shortcut dan pixel/UI browser belum dibekukan melalui browser test.
- Payload `details[]`: account_code (string), helper_code (nullable string), position (`DEBET`/`KREDIT`), amount. Minimal dua baris; satu posisi per baris, bukan dua kolom nominal debit/kredit.
- Amount legacy minimum 0.01, maksimal dua desimal, dinormalisasi fixed-decimal string oleh JournalAmount. `1.250,50` → `1250.50`; nominal kosong/negatif/precision berlebih tidak boleh dianggap nol yang valid.
- Foreign currency memakai branch storeForeignJournal bila multicurrency flag aktif; bukan bukti semua jalur memakai validasi currency identik.
- Account code/helper code merupakan relasi aktual; jangan mengganti seluruh FK menjadi account_id tanpa migrasi terencana.
- Header manual journal_type MANUAL; model membentuk evidence number, fixture tanggal 2026-10-01 menghasilkan GJ-20261001-0001.
- Total debit/kredit diperiksa sebelum write; legacy store dibungkus transaksi. Tidak ada source_line_id/idempotency event universal yang boleh diasumsikan tersedia.

## Output dan histori

Header/detail menjadi sumber GL; normal balance mengutamakan master (termasuk kontra-akun), fallback prefix jika kosong. Export membaca ledger yang sama. Rancangan target “hanya posted” belum boleh diterapkan sebagai filter baru tanpa pemetaan status data lama.

Manual journal masih mempunyai alur edit/delete. SourceJournalProtection dan protected builders melindungi sejumlah sumber, tetapi tidak sama dengan immutable posted universal. Period guard secara eksplisit testing-only. Fixture offset manual mempertahankan original dan menetralkan saldo; **bukan** pembuktian reversal tertaut/otorisasi reversal.

## Fixture sintetis dan batas test

`D:\xampp\htdocs\app-akuntansi-portfolio-master\tests\Fixtures\integration\matrix-baseline.php`: two-line, multi-line, helper. `D:\xampp\htdocs\app-akuntansi-portfolio-master\tests\Feature\IntegrationMatrixBaselineTest.php`: normalisasi, jumlah/baris/detail, source reference, evidence number, credit-normal ledger export dan offset manual.

Middleware dimatikan mengikuti pola fixture controller; test ini tidak membuktikan authorization. Tests yang sudah ada mencakup JournalBalanceTest, ManualJournalCurrencyPrecisionTest, LedgerPageTotalsTest, FxBaselineSnapshotTest, AccountingPeriodLifecycleTest dan GrnReceivingTest. Multi-periode, saldo awal, report totals, FX dan reversal harus tetap diperluas pada gate berikutnya; tidak mengklaim cakupan lengkap dari empat test baru.
# B01 — Discovery dan baseline (2026-10-26)

## Status dan batas bukti

**B01 didokumentasikan; gate belum lulus. B02–B12 belum dijalankan.**
Repository: `D:\xampp\htdocs\app-akuntansi-portfolio-master`; baseline branch `main`; HEAD `a8f9fe5` (prefix). B01.1 dikerjakan pada branch `fix/b01-1-baseline-regressions` dari HEAD tersebut. Penghapusan `D:\xampp\htdocs\app-akuntansi-portfolio-master\template_saldo_awal.csv` sudah ada sebelum pekerjaan ini dan tidak disentuh. Tidak membuat commit, migration, atau mengubah transaksi operasional. Tidak ditemukan AGENTS.md dalam pemeriksaan repository; RULES.md dibaca.

Referensi rancangan: `D:\Project ERP\Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt`, ketentuan utama, bagian terkait dan B01. PDF dan ZIP tersedia di direktori referensi, tetapi hash/parity/runtime v33 belum diverifikasi ulang pada B01. Hasil audit lama bukan hasil test sesi ini.

Stack aktual: Laravel 12.61.1 (lockfile), PHP 8.2.12, PHPUnit 11.5.55; UI ERP Blade. Koneksi lokal MySQL tidak digunakan untuk test; seluruh test sesi ini dipaksa SQLite `:memory:`. Source v33 React/Workers bukan paket Laravel siap dipasang.

## Peta source

Semua suffix berikut relatif terhadap root absolut repository di atas; ini menunjuk file aktual, bukan nama tabel usulan audit.

| Domain | File / fungsi aktual | Tabel / event |
|---|---|---|
| Route jurnal | `routes/web.php`, `jurnal.create/store/import`, `buku-besar.index/export` | GET form, POST jurnal, export |
| UI | `resources/views/journal/create.blade.php` | Header dan `details[]` berulang; bukan 12 blok |
| Manual journal | `app/Http/Controllers/JournalController.php::store` | create `journal_headers`, `journal_details`; normalisasi dan balance, outer transaction |
| Ledger models | `app/Models/JournalHeader.php`, `JournalDetail.php` | Key `journal_id`; account/helper code; model/builder protection |
| Posting existing | `app/Support/PostingService.php::post` | Shared writer sebagian modul; bukan semua; transaksi internal hanya cabang FX |
| GL | `app/Http/Controllers/LedgerController.php::accountData`, `app/Exports/LedgerExport.php::array` | Join headers/details; opening, running, closing; normal_balance master diprioritaskan |
| Reports | `app/Http/Controllers/BalanceSheetController.php`, `ProfitLossController.php`, `CashFlowController.php` | Konsumen ledger; parity semua laporan belum dibuktikan fixture B01 |
| Company | `app/Modules/Platform/Support/OperationalCompany.php::company/authorizeRequest/id` | Tepat satu MGI aktif; selected-company membership; shared stock/journal belum multi-tenant |
| Master | `app/Modules/Platform/Models/Company.php`, `Party.php`, `LegacyMap.php`; `app/Models/Account.php` | companies, parties, legacy_map, accounts; jangan membuat master paralel |
| GRN A3 | `app/Services/GrnReceivingService.php::receive`; `app/Http/Controllers/PurchaseReceiptController.php` | purchase_receipts, PO, bill, stock/journal lama; company lock, UUID+hash retry |
| Protection | `app/Support/ProtectedJournalQuery.php`, `SourceJournalProtection.php::check` | Guard query/model dan source-linked journals; bukan bukti universal posted immutability |
| Period | `app/Support/AccountingPeriodGuard.php::enabled/dates/source`; `app/Services/AccountingPeriodService.php` | accounting_periods; enabled membutuhkan flag DAN environment testing |
| Stock | `app/Models/InventoryLedger.php`; `app/Modules/Manufacturing/Models/MaterialLedger.php` | inventory_ledgers dan material ledger terpisah; belum satu mutation ledger universal |
| Payment lama | `app/Http/Controllers/PaymentPlanController.php::updateStatus/postJournal` | transaksi_payment_plan; direct journal writer; bukan domain parity v33 |
| Service deprecated | `app/Services/PaymentPlanService.php` | Secara eksplisit ORPHAN / DO NOT USE; bukan adapter yang boleh diaktifkan |

## Jalur dan writer yang harus direkonsiliasi

Manual input → `JournalController::store` → `JournalAmount::normalize` → balance validator → DB transaction → header/detail → GL/export. FX mempunyai cabang tersendiri. Tidak ada tahap posted universal yang boleh diasumsikan pada semua journal lama.

Shared PostingService dipanggil antara lain PurchaseBillController, PurchaseOrderService, SalesOrderService, AssetDepreciationService, FxPostingService, MaklunReceiptService. Writer langsung juga ditemukan pada JournalController (124/152/270), PaymentPlanController (601/614/621), AccountController, SalesInvoiceController, SalesReturnController, PurchaseReturnController, WarehouseController; serta command FastImportBIL/INV/PR/SR. Nomor baris adalah snapshot HEAD, bukan kontrak stabil. InvoiceCancellationService dan InvoicePaymentAllocationService juga memerlukan pemeriksaan writer saat konsolidasi. Search call-site bukan bukti semua jalur runtime aman.

## GRN yang benar-benar ada

A3 sudah tersedia dan test SQLite terkait lulus. `receive` memvalidasi UUID, hash payload, status PO, company scope, duplicate bill dan mapping akun; retry sama mengembalikan receipt lama. V1 menerima kuantitas **integer**, pembelian AP tanpa DP/pajak/diskon. Jangan mengklaim QC, decimal qty, three-way match lengkap atau pemisahan GRN/invoice sudah ada. Perluas service ini pada B06, bukan membuat receiving paralel. Config lokal menunjukkan GRN aktif; tidak diubah.

## Gate belum terpenuhi

1. B01.1 memperbaiki fixture/test tanpa runtime edits; full suite kini hijau (626 tests / 13954 assertions). Coverage baseline B01.2 belum lengkap; lihat test-evidence.md.
2. Backup/restore staging DB+blob belum tersedia sebagai bukti yang dijalankan sesi ini.
3. MySQL concurrency, browser/shortcut visual dan seluruh laporan belum diuji.
4. Single-company MGI adalah batas operasional yang disepakati, bukan blocker multi-tenant. Period lock production adalah gap runtime penting. Idempotency universal, posted immutability dan reversal tertaut tetap gap target B03; tidak wajib dibangun seluruhnya dalam discovery B01.

Tidak melanjutkan patch B02/B03 berdasarkan asumsi multi-company atau matriks 12 blok.
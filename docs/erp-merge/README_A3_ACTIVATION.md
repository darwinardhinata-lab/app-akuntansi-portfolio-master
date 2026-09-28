# A3 Activated — GRN MGI baseline

Tanggal aktivasi: 25 September 2026. Baseline source A2 sebelumnya adalah
`020D5346B0B51E313F84F1B9C688DF92EDD7E8A4E2A9F1ED217A6B17929F410F`.

## Runtime aktif

- Database Laravel: `mgi_fresh_20260924`; company tunggal `1 / MGI`.
- `PLATFORM_ORDER_COMPANY_SCOPE_ENABLED=true` dan `PLATFORM_GRN_ENABLED=true`.
- Legacy sync dan CEISA tetap `false`; session/cache/queue tetap database.
- Migration A3 tunggal `2026_09_25_090001_add_grn_posting_contract` terpasang batch 7.
- Readiness akhir: `READINESS=PASSED; scope=ON` dan
  `GRN_READINESS=PASSED; flag=ON; posted=0`.

## Mapping posting GRN yang disetujui

GRN V1 memakai mapping khusus MGI, tanpa mengubah master COA atau mapping
receiving legacy:

- debit persediaan: `114001` — Barang Jadi;
- kredit hutang: `211001` — Utang Usaha.

`GrnReceivingService` membuat Bill dan detail Bill dengan kedua code tersebut,
kemudian meneruskannya eksplisit ke posting legacy agar kedua line jurnal GRN
memakai code yang sama. Ketika parameter ini tidak ada, jalur legacy tetap
memakai `config('coa.persediaan')` dan `config('coa.hutang_usaha')` seperti
sebelumnya.

## Deviasi source lokal dari paket A3 awal

Sepuluh file berikut adalah baseline A3 Activated dan tidak boleh ditimpa oleh
ZIP A3 awal:

| File | SHA-256 aktif |
|---|---|
| `app/Services/GrnReceivingService.php` | `5B84CB87402F2D18C1BC7992CB3857D19544C4EE5CA53A82D15A8B3286360AD1` |
| `app/Services/PurchaseOrderService.php` | `13366E31E0DCB6A9A6967764B3A13C159A661D4B8E500E0BCC2545BDB5BB593F` |
| `app/Console/Commands/CheckGrnReadiness.php` | `94AADA9C8B1CEFAA8D00ED97B7521864A53A88D1C3C41422B402B914B3BCB029` |
| `config/platform.php` | `5BC50DF347C2115A7247C17E76886A4A23F60DF766C831C4DF70BE5072B8D0E0` |
| `tests/Concerns/GrnScenarios.php` | `6462FEC2F2FD30E20970383899D358CC5244F101AC14C5F2547546707E3B712F` |
| `tests/Feature/GrnReceivingTest.php` | `C9A23E4BC8ABB18320E9A67FECF2420789A1171AEF2A4D63B5EB9FD9D081566A` |
| `tests/Integration/GrnReceivingMysqlTest.php` | `C197DFA393BA819D9D515D1683F4B3A674859A7133ED9E51C5C9C07D538888E3` |
| `phpunit.xml` | `B8203AAA2CAD206CB8998EAE2F95BF47993C7FF764A50F3A3343AFC9DEE8B520` |
| `phpunit.a3-mysql.xml` | `885875EA8453CCC1AC65A021B19723320AEFA91973A67E9D68129DDA4A73F463` |
| `tools/verify-platform-a3.ps1` | `C397754A3BE2EE4AE7AA05D3DCFE7B2589D069DCB4A48C4A84F5D7CCCD026DF4` |

Perubahan PHPUnit/verifier mengisolasi proses test dari
`storage/framework/down` produksi dengan maintenance cache `array`; perubahan
ini tidak mengubah maintenance, `.env`, cache, atau database runtime MGI.

## Hasil test aktual

| Gate | Hasil |
|---|---:|
| Targeted SQLite in-memory | `OK (76 tests, 414 assertions)` |
| Full SQLite in-memory | `OK (160 tests, 764 assertions)` |
| MySQL fixture + concurrency | `OK (20 tests, 163 assertions)` |

Fixture MySQL memakai nama acak `mgi_fresh_a3test_<16 hex>` dan dua child
process untuk concurrency; fixture dan child process telah dibersihkan.
`git diff --check` lulus.

## Preservasi dan backup

Backup sebelum aktivasi ada di
`D:\ERP_Backup\MGI_PRE_A3_20260925_091300`; dump dan `.env` sudah diverifikasi
hash serta restore-test ke fixture lalu fixture dihapus. Saat aktivasi dan
verifikasi akhir: COA 208, user 3, company 1, Party grant DARWIN tetap tiga
permission dan tidak ada grant Party itu untuk user 1/2. Count PO/SO/GRN/Bill/
jurnal/ledger tetap nol.

## Batas V1 dan tindak lanjut

Gunakan hanya pembelian hutang tanpa uang muka, pajak, atau diskon dan satu
GRN untuk satu Bill baru. Tidak ada reversal, approval terpisah, tutup periode,
alokasi banyak GRN ke satu Bill, atau Payment Request. Jangan menghapus GRN
posted atau jurnalnya; tahap berikutnya adalah reversal yang menjaga jejak
audit sebelum memperluas alokasi Bill/Payment Request.

Smoke tanpa autentikasi telah memeriksa login (`200`) dan route GRN/PO/gudang/
Bill (`302` ke login). Login DARWIN, pilihan MGI, dan halaman terautentikasi
belum diuji manual karena kredensial tidak ditebak, dicetak, atau direset.
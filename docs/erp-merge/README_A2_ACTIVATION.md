# A2 MGI — Catatan penerimaan dan baseline source

Tanggal aktivasi: 24 September 2026.

## Status runtime

- Database aktif: `mgi_fresh_20260924`.
- Company operasional: `1 / MGI / PT. Magicase Group Indonesia` (aktif).
- `PLATFORM_ORDER_COMPANY_SCOPE_ENABLED=true` dan readiness terakhir:
  `READINESS=PASSED; scope=ON`.
- Legacy sync dan CEISA tetap nonaktif.
- A2 tetap **single-company**; ini bukan isolasi multi-company menyeluruh untuk
  stok, jurnal, invoice/bill, retur, atau laporan.

## Perbaikan yang menjadi baseline setelah paket A2 awal

Paket A2 awal tidak boleh menimpa tiga perubahan berikut. Ketiganya sudah diuji
ulang pada source aktif.

1. `app/Http/Controllers/SalesOrderController.php`
   - Simpan dan edit SO menormalkan `source` kosong menjadi `MANUAL`.
   - Alasannya: kolom `sales_orders.source` adalah `NOT NULL` dengan default
     schema `MANUAL`; mengirim `NULL` eksplisit melanggar constraint dan
     menyebabkan simpan SO gagal.
2. `database/migrations/2026_09_24_150001_add_company_id_to_purchase_orders.php`
3. `database/migrations/2026_09_24_150002_add_company_id_to_sales_orders.php`
   - Pada `down()`, urutan MySQL adalah `dropForeign`, `dropIndex`, lalu
     `dropColumn`.
   - Alasannya: InnoDB dapat memakai index gabungan company/tanggal sebagai
     index foreign key, sehingga menghapus index sebelum FK menghasilkan error
     1553. `up()` migration tidak berubah.

## Gate yang lulus pada baseline ini

| Gate | Hasil |
| --- | --- |
| `tools/verify-platform-a2.ps1` | `OK (55 tests, 243 assertions)` |
| `tools/verify-platform-a2.ps1 -FullSuite` | `OK (139 tests, 593 assertions)` |
| `tools/verify-platform-a2.ps1 -MySqlIntegration` | `OK (4 tests, 14 assertions)` |

Tes MySQL memakai database fixture acak `mgi_fresh_a2test_<16 hex>` dan
membersihkannya setelah selesai. Tidak ada transaksi dummy yang dibuat di
database MGI kerja.

## Aktivasi yang tercatat

Hanya dua migration A2 berikut yang dijalankan pada MGI:

- `2026_09_24_150001_add_company_id_to_purchase_orders`
- `2026_09_24_150002_add_company_id_to_sales_orders`

Saat verifikasi setelah aktivasi: COA tetap 208, pengguna tetap 3, grant Party
khusus `PARTY_U3_C1` tetap hanya untuk user 3/company 1, serta PO/SO dan count
bisnis terkait tetap nol.

Backup sebelum aktivasi disimpan di luar web root. Jangan memasukkan `.env`,
dump SQL, `vendor`, `node_modules`, atau runtime `storage` ke paket source.
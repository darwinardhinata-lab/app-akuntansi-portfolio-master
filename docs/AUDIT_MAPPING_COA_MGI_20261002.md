# Audit mapping COA lintas modul — MGI

## Sumber dan batas

- Root source: `d:\xampp\htdocs\app-akuntansi-portfolio-master`.
- Workbook: `d:\Project ERP\coa_export_20261002020629.xlsx`, 211 akun unik.
- Source baseline main a9e354d dengan perubahan lokal tahap Payment Plan.
- Audit source/workbook, bukan audit isi database atau jurnal historis.
- Tidak ada query database operasional, bootstrap --apply, migration, import,
  perubahan config mapping, atau perubahan .env.
- .env tidak memiliki baris COA_* pada pemeriksaan. CLI bootstrap sebelumnya
  mewarisi SQLite :memory: dari test; bukan bukti runtime produksi. Runtime web,
  environment server dan tabel company_coa_mappings masih perlu verifikasi.

## Kesimpulan terukur

32/32 default key config/coa.php tidak terdapat dalam workbook. Semua merupakan
kode legacy; 20/20 kandidat CompanyCoaRegistry::manufacturingMgi tersedia dan
saldo normal cocok. Kandidat registry tidak membuktikan assignment DB aktif,
persetujuan kebijakan, atau seluruh jalur manufaktur memakai resolver.

## Matriks default legacy dan kandidat MGI

Kandidat di bawah bukan mapping yang telah diterapkan. DEBET/KREDIT adalah saldo
normal, bukan larangan menggunakan sisi sebaliknya pada reversal/transaksi sah.

| Key config/coa.php | Default legacy (ABSENT) | Kandidat dari workbook / keputusan |
|---|---|---|
| piutang_usaha | 11100 | 113101 Piutang Usaha; DEBET, NERACA |
| persediaan | 11200 | 114001 barang jadi hanya untuk barang jadi; klasifikasi item lain perlu mapping |
| uang_muka_beli | 11305 | 116002 Uang Muka Ke Pemasok; DEBET, NERACA |
| hutang_usaha | 22000 | 211001 Utang Usaha; KREDIT, NERACA |
| penjualan | 44000 | 411001 lokal / 411002 ekspor / 411003 jasa; harus memilih menurut transaksi |
| diskon_penjualan | 44001 | 411005 Diskon Penjualan; 411004 Cash Diskon berbeda fungsi; DEBET, LABA RUGI |
| diskon_ongkir | 66499 | Tidak ada padanan eksplisit; kebijakan kontra-revenue/biaya perlu disahkan |
| diskon_lain | 44002 | Jangan gabungkan retur dan diskon otomatis; 411005 vs 411006 |
| ongkos_kirim | 77005 | 810007 Shipping & Handling kandidat pendapatan; KREDIT, LABA RUGI |
| biaya_lain | 44004 | Source memakai KREDIT untuk otherCost; kandidat 810003 hanya jika pendapatan lain, bukan beban |
| hpp | 55000 | 510001 Beban Pokok Penjualan; DEBET, LABA RUGI |
| pajak_keluaran | 22103 | 213108 PPN Keluaran; KREDIT, NERACA |
| pajak_masukan | 11303 | 117008 PPN Masukan; DEBET, NERACA |
| aset_tetap | 12000 | 121001–121011 per kategori; bukan satu akun global |
| akum_penyusutan | 12001 | 122001–122009 sesuai kategori; KREDIT, NERACA |
| beban_penyusutan | 88002 | 630001–630009 sesuai kategori; biaya produksi/penjualan perlu kebijakan cost center |
| biaya_kirim | 66281 | Kandidat 510011 / 610008 / akun biaya terkait; tidak boleh pilih hanya berdasarkan kemiripan nama |
| retur_penjualan | 44010 | 411006 Retur Penjualan; DEBET, LABA RUGI |
| retur_shopee | 44006 | Tidak ada akun channel; kandidat 411006 dengan dimensi channel bila masih diperlukan |
| retur_tiktok | 44008 | Sama; review apakah flow channel masih relevan MGI |
| kerugian_barang_cacat | 88004 | 510004 adjustment vs 910003 kerugian; perlu kebijakan normal/abnormal loss |
| pembulatan | 88068 | 910008 Selisih Pembulatan; DEBET, LABA RUGI; materialitas harus ditetapkan |
| persediaan_bahan_baku_benang | 11210 | 114003 bahan baku kandidat; bedakan item/subledger bila tidak ada akun yarn tersendiri |
| persediaan_bahan_baku_kain | 11220 | 114003 / 114008 / 114002 sesuai tahap produksi; tidak otomatis sama dengan yarn |
| wip_produksi | 11500 | 114002 Barang dalam proses; DEBET, NERACA |
| persediaan_barang_jadi | 11200 | 114001 Barang jadi; DEBET, NERACA |
| hutang_usaha_maklun | 22010 | 212001 accrual subcontractor vs 211001 AP setelah invoice; bedakan event |
| biaya_jasa_knitting | 11500 | Mapping WIP/capitalization menurut event; bukan langsung beban |
| biaya_jasa_proses_kain | 11500 | Sama; kebijakan capitalized cost diperlukan |
| biaya_jasa_jahit | 11500 | Sama; jangan pilih akun beban hanya dari nama key |
| kerugian_wastage_produksi | 88005 | 510004 / akun abnormal loss; perlu kebijakan scrap/waste |
| selisih_produksi | 88006 | Tidak ada padanan eksplisit; definisikan variance vs adjustment |

## Jalur terdampak dan prioritas

### P0 — posting dan laporan inti

- app/Services/SalesOrderService.php: AR, diskon, penjualan, ongkir, VAT, HPP,
  inventory memakai config legacy. SalesInvoiceController memiliki jalur serupa.
- app/Services/PurchaseOrderService.php: jalur legacy memakai inventory/AP/advance
  global; GRN V1 override inventory 114001 dan AP 211001 tersedia dalam workbook.
  Override itu hanya benar untuk lingkup GRN V1 yang sudah dibatasi.
- PurchaseBillController: VAT input global; debit detail/credit dipilih pengguna.
- PaymentPlanController: source IDR sudah eksplisit MGI, tetapi debit pembayaran
  hutang masih config('coa.hutang_usaha') legacy. Perbaikan source bank saja belum
  menyelesaikan pembayaran AP.
- PurchaseReturnController dan SalesReturnController memakai mapping legacy.
- WarehouseController inbound/outbound memakai inventory global dan offset user.
- FastImportBIL/INV/PR/SR memakai beberapa mapping legacy; jangan menjalankan
  import operasional sebelum mapping dan idempotensi/divisi dokumen direview.
- AdvancedReportController: uang muka penjualan memakai hutang_usaha (baris 76),
  salah semantik bahkan jika kode AP diganti. Workbook punya 231001 Uang Muka
  Penjualan. Tambahkan semantic key terpisah, jangan memakai AP.
- AccountController template saldo awal: baris 477 memakai piutang_usaha tetapi
  label Kas Besar. Label harus berasal master/semantic key yang tepat.

### P0/P1 — aset

- JournalController (146/234) dan JournalCsvImportService (452) mendeteksi aset
  dengan satu akun global. Workbook mempunyai banyak kategori 121xxx.
- AssetController (267/283) memakai akun akumulasi/beban global untuk penyusutan.
  Diperlukan matrix kategori aset → asset/accumulated/depreciation expense.
- assets:sync memakai prefix 12% secara luas. Ini dapat memasukkan debit pada akun
  akumulasi, deferred tax, software/CIP sebagai aset depresiasi tanpa klasifikasi.
  Jangan jalankan sync hanya karena seluruh akun 12% dianggap fixed asset.

### P1 — manufaktur campuran

Registry 20 kandidat sesuai workbook: 114003,114004,114008,114002,114001,114005,
114007,116002,117008,211001,212001,212002,212004,212005,213108,411001,411002,
510001,510004,510010. Semua saldo normal cocok. Lihat
app/Modules/Platform/Support/CompanyCoaRegistry.php.

CompanyCoaResolver fail-closed atas mapping missing/inactive/account missing/wrong
normal. Namun belum memvalidasi fungsi semantik atau report_pos: akun lain dengan
saldo normal sama dapat lolos. CheckCoaMapping punya batas yang sama.

MaterialReceiptService, CuttingOrderService, StitchingOrderService,
AuxiliaryMaterialIssueService dan completion WorkOrder memakai resolver.
KnitOrderService (148–152) dan ProcessingOrderService (130–134) masih memakai config
legacy. Routes knit/processing masih terdaftar. YarnImport/FabricImport dan
WorkOrderService create (47) juga menyimpan default legacy ke master/dokumen.
Status serta pemakaian aktual jalur ini belum diverifikasi di database.

### P1/P2 — laporan, master, dan residu dormant

- CashFlowController/DashboardController memilih tipe Cash & Bank secara luas;
  workbook juga memberi tipe tersebut pada deposito. Status cash equivalent dan
  maturity harus ditentukan sebelum menyebut semua deposito cash flow cash.
- BudgetingService sudah memakai AccountClassifier dengan normal_balance; tidak
  ditemukan hardcode prefix 8=expense pada bagian yang dibaca. Jangan mengulang
  temuan dokumen lama tanpa memeriksa source sekarang.
- SyncProductDashboardJob menyimpan inventory 11200/HPP 55000; ada legacy sync
  flag guard. Jangan mengaktifkan kembali hanya untuk memperbaiki master MGI.
- AccountSeeder memakai akun contoh yang berbeda dari workbook. Tidak dipanggil
  DatabaseSeeder yang diperiksa; risiko jika operator menjalankannya langsung.
- PaymentPlanService ditandai deprecated/orphan, termasuk cash memakai AR;
  bukan jalur aktif dan tidak boleh diaktifkan ulang.

## Catatan kualitas COA sumber

- 117007 bernama "Utang PPh 4 ayat 2" tetapi DEBET/NERACA dalam pajak bayar dimuka;
  213107 merupakan PPh 4 ayat 2 KREDIT. Finance perlu memperjelas nama 117007.
- 620035 dan 910004 sama-sama biaya administrasi bank: fungsi operasional vs
  nonoperasional perlu dibedakan, bukan disatukan otomatis.
- 411004/411005/411006 normal DEBET walaupun kelompok Sales; 510002/510003 normal
  KREDIT walaupun COGS. Validator harus mendukung kontra-account, bukan memaksa
  saldo normal dari prefix saja.

## Rencana perbaikan (belum diterapkan)

1. Verifikasi environment COA runtime dan tabel company mappings read-only pada
   database MGI; catat mapping, akun, normal/report_pos dan event jurnal.
2. Disahkan Finance: AR/AP/VAT/advance/HPP/FG/rounding dan kebijakan sales lokal vs
   ekspor. Key ambigu tetap fail-closed, tanpa fallback akun lama.
3. Satukan resolver per-company lintas modul; tambahkan report_pos serta registry
   semantic eligibility. Jangan sekadar mengganti semua nomor lewat search-replace.
4. Perbaiki Payment AP, penjualan/retur, purchase legacy, warehouse, dan laporan
   uang muka sebagai paket kecil dengan regression test setiap jalur.
5. Mapping aset per kategori, kemudian knitting/processing/master item defaults.
6. Guard seeder/import/sync dormant; audit data historis terpisah, tanpa menghapus
   jurnal atau menomori ulang otomatis.

Acceptance: semua mapping aktif ada di master, semantic/normal/report_pos sesuai;
jurnal balanced dan akun tepat; report subledger cocok GL; missing mapping ditolak
tanpa perubahan stock/Bill; tidak ada fallback legacy; historical data tidak berubah.

## Validasi audit

Perbandingan regex atas 32 default source dan 20 kandidat registry terhadap seluruh
211 akun workbook dilakukan read-only. Test existing CompanyCoaMappingTest dan
PaymentFundingAccountTest dijalankan pada SQLite in-memory, bukan data produksi.
Test tersebut membuktikan guard yang diuji, tidak membuktikan seluruh mapping
operasional telah benar. Hanya laporan audit ditambahkan pada tahap ini.
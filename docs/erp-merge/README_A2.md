# ERP Marvel-Dard A2 — Kepemilikan PO/SO MGI

Tanggal paket: 24 September 2026. Baseline: paket A1 + MGI Fresh Start yang telah dikonfirmasi terpasang. Database aktif yang dilaporkan operator: `mgi_fresh_20260924`.

## 1. Tujuan dan batas hasil

A2 menambahkan identitas pemilik perusahaan pada header PO/SO baru, serta menampilkan `config('app.name')` pada judul dan heading login. COA, akun pengguna/password, role dan grant Party tidak diubah oleh paket.

**A2 masih deployment satu perusahaan MGI.** Stok, jurnal, invoice/bill, Payment Plan, retur, manufacturing, dan laporan belum mempunyai isolasi multi-company lengkap. Jangan membuat perusahaan kedua atau mengiklankan A2 sebagai ERP multi-company. Guard menolak operasi web terautentikasi jika terdapat lebih dari satu record company, termasuk company nonaktif.

Paket dibuat dari source, bukan dari database kerja. Tidak ada akses atau perubahan langsung ke Windows/XAMPP operator. PHP, Composer, PowerShell, dan MySQL tidak tersedia di lingkungan pembuat paket: **PHP lint, PHPUnit, Blade, dan integrasi MySQL A2 belum dijalankan di sini.** Pemeriksaan hash, diff, struktur paket, dan patch dilakukan secara statis. Verifikasi runtime berikut adalah gate wajib sebelum aktivasi.

## 2. Perubahan aplikasi

- Dua migration additive menambahkan `company_id` nullable + foreign key ke `companies` dengan restrict-delete, dan index gabungan company/tanggal pada PO serta SO. Tidak mengubah baris historis atau mengisi owner secara otomatis.
- Flag `PLATFORM_ORDER_COMPANY_SCOPE_ENABLED` default `false`, sehingga source dapat dipasang sebelum schema. Dalam maintenance, aktifkan hanya setelah migration khusus A2 dan readiness lulus.
- Ketika flag aktif, model PO/SO membatasi query ke MGI, memberi owner pada create server-side, serta menolak save/delete model yang tidak memiliki owner yang sesuai. Owner tidak ditambahkan ke `$fillable`.
- Perubahan owner pada model existing ditolak. Party baru/berubah harus aktif, milik MGI, dan mempunyai role supplier/subcontractor untuk PO atau customer untuk SO. Link tidak berubah boleh tetap menunjuk Party nonaktif; owner Party tetap diperiksa.
- HTTP memerlukan membership dan company session yang valid. Company picker `/platform/company`, login/logout dan pergantian bahasa tetap dapat diakses. Route Platform tetap menggunakan policy A1. Tidak ada pemberian izin otomatis kepada role ADMIN.
- Form manual create/edit PO/SO membawa `context_company_id`; token tidak ada/salah menghasilkan 409, payload `company_id` menghasilkan 422. Perlindungan ini tidak menggantikan CSRF.
- Command/import berbasis model menggunakan satu company MGI yang tervalidasi. Sinkronisasi legacy dan CEISA harus tetap nonaktif. Command operator tidak menggunakan session browser.
- Raw update total SO pada import diberi kondisi company. Penghapusan invoice yang terkait SO memeriksa owner SO sebelum mengubah jurnal/stok. Invoice standalone tetap mengikuti alur legacy satu perusahaan.
- Retur pembelian hanya dapat memakai detail milik PO yang dipilih.
- Simpan PO manual diselaraskan dengan schema (`item_code`, description, qty_received, amount) dan redirect diperbaiki ke `po.index`. Import PO menggunakan `qty`, menghapus kolom `subtotal` yang tidak ada di schema detail. Validasi SKU ditambahkan pada simpan/edit PO/SO. Rumus pajak/header dan posting service tidak diubah.

`PurchaseOrderService`, `SalesOrderService`, `PostingService`, `PurchaseBillController`, konfigurasi COA, composer dependencies, migration lama, serta file `.env` tidak berubah dalam arsip. Tidak ada aktivasi GRN atau Payment Request baru, backfill, reset ulang MGI, import BBW, maupun perubahan profil resmi.

## 3. Batas teknis yang perlu diketahui

Global scope Eloquent berlaku pada model header; query SQL langsung dan `withoutGlobalScopes()` dapat melewatinya. Ini bukan row-level security database. Jangan menambah raw writer PO/SO tanpa owner server-side dan pemeriksaan perusahaan. `PaymentPlanService` legacy/deprecated yang tidak ditemukan pemanggilnya masih mempunyai jalur SQL lama; jangan diaktifkan kembali tanpa review. Alur aktif Payment Plan controller memakai model PO.

Nomor PO/SO tetap unik global sesuai schema sebelumnya. Tidak ada dukungan nomor sama pada perusahaan berbeda. Guard satu perusahaan merupakan batas transisi untuk melindungi modul bersama, bukan pengganti migrasi multi-company seluruh ERP.

Kolom nullable diperlukan untuk pemasangan bertahap. Jika flag belum aktif, alur lama dapat menghasilkan owner NULL. Karena itu jangan membuka aplikasi di antara pemasangan migration, readiness, dan aktivasi flag. Data yang sudah dibuat sebelum A2 harus ditinjau; command readiness tidak akan mengklaim atau menghapusnya.

Portal pengajuan publik tidak memperoleh policy baru dalam paket ini. Profil MGI/PIN dan permission Party tetap sesuai hasil cutover. User baru tanpa membership harus mendapat membership eksplisit; role ADMIN saja tidak melewati guard.

## 4. Instalasi berdasarkan manifest

1. Ekstrak ZIP ke direktori staging **di luar repository aktif**.
2. Baca `docs/erp-merge/CHANGED_FILES_A2.json` di source paket. Untuk tiap file `modified`, bandingkan SHA-256 repository dengan `baseline_sha256`. Jika sudah sama dengan `result_sha256`, tandai sudah terpasang. Untuk file `added`, pastikan belum ada atau hash hasil sama. Jika berbeda, review dan merge; jangan menimpa perubahan lokal.
3. Pasang hanya file pada array `files`. Salin juga semua `metadata_files`, termasuk `CHANGED_FILES_A2.json` yang sengaja tidak menyimpan hash dirinya sendiri.
4. Jangan menyalin seluruh source ZIP ke repository. Source lengkap hanya untuk referensi. `.env`, vendor, storage runtime, database, backup, dan ZIP lokal tidak menjadi bahan overwrite.
5. `A2_CHANGES.patch` tersedia sebagai alternatif patch relatif terhadap baseline MGI. Pilih copy manifest atau apply patch; jangan melakukan keduanya. `git apply --check` harus lulus sebelum apply.
6. Review `git diff`, file untracked, dan `git diff --check`. Jangan `git add .`; jangan menyertakan arsip, kredensial, atau backup dalam commit. Tidak perlu push untuk validasi lokal.

File dokumentasi A1 sebelumnya berada pada:
`D:\xampp\htdocs\app-akuntansi-portfolio-master\docs\erp-merge\README_A1.md`

## 5. Pengujian terisolasi sebelum maintenance

Dari repository aktif:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1 -FullSuite
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1 -MySqlIntegration
```

Verifier mengisolasi konfigurasi testing dengan SQLite `:memory:`, session/cache array, queue sync, dan path config cache sementara. Flag A2 diatur OFF pada environment test umum; test A2 mengaktifkannya secara eksplisit melalui config. Verifier memulihkan environment process sesudah selesai dan tidak menulis `.env`.

- `OrderCompanyOwnershipTest`: create/edit manual, SKU, stale form, owner forgery, membership, company tunggal, sync/customs guard, dokumen tanpa owner, scope query OR/bulk update, model import, immutability, Party role, receive/ship tanpa posting, invoice delete, CSV PO/reimport, retur lintas header, login dan readiness SQLite.
- `PlatformAccessTest` dan `PartyLinkageTest`: regresi A1. Payload SKU test A1 disesuaikan agar tetap menguji validasi Party, bukan gagal lebih awal pada field SKU.
- `OrderOwnershipMysqlTest`: dua migration A2 yang sebenarnya dijalankan pada schema fixture minimal; owner model, readiness, anomaly detection, FK restrict, dan migration down di fixture.

MySQL integration hanya menggunakan database baru `mgi_fresh_a2test_<16 hex>` yang dibuat test sendiri dan dibersihkan sesudahnya. Koneksi admin memakai `MGI_TEST_HOST`, `MGI_TEST_PORT`, `MGI_TEST_USER`, `MGI_TEST_PASSWORD` (fallback lokal 127.0.0.1:3306/root/password kosong). Jangan mencetak password. Suite ini tidak memakai `mgi_fresh_20260924` atau `db_akuntansi`. Fixture minimal tidak membuktikan semua route legacy; lakukan smoke test pada clone MGI terpisah bila dibutuhkan.

Jangan melanjutkan bila test/lint gagal. Perbaikan yang diperlukan harus ditinjau, diuji ulang, dan dicatat sebagai deviasi hash paket. Jangan mengklaim jumlah test/asserasi lulus dari dokumen ini; tulis output aktual.

## 6. Aktivasi pada target MGI

Sesudah semua gate pengujian lulus:

1. Verifikasi runtime database benar-benar `mgi_fresh_20260924`, company tunggal ID 1 code MGI aktif. Catat count/digest COA dan pengguna serta snapshot grant sebelum perubahan. Pastikan legacy sync/CEISA tetap false.
2. Masuk maintenance dengan `php artisan down`, hentikan import/worker/scheduler dan pastikan tidak ada penulis aktif.
3. Backup database **MGI saat ini** dan `.env` di luar web root, nama timestamp baru; jangan menimpa backup BBW. Periksa hash dan restorability pada fixture terpisah. Jangan mengandalkan backup BBW untuk memulihkan data baru MGI.
4. Pastikan transaksi PO/SO masih kosong seperti laporan cutover. Jika sudah ada data, hentikan aktivasi untuk review ownership; jangan delete, truncate, atau backfill otomatis.
5. Jalankan hanya dua migration berikut setelah memverifikasi koneksi runtime. Jangan menjalankan `migrate` tanpa path karena migration lain mungkin pending:

```powershell
php artisan migrate --path=database/migrations/2026_09_24_150001_add_company_id_to_purchase_orders.php --force
php artisan migrate --path=database/migrations/2026_09_24_150002_add_company_id_to_sales_orders.php --force
php artisan platform:check-order-ownership
```

`--force` di sini hanya mengizinkan Artisan menjalankan migration terarah pada environment production. Jangan memakainya untuk melewati gate verifikasi.

6. Hasil harus `READINESS=PASSED; scope=OFF` (atau ON jika sudah aktif sebelumnya), seluruh kolom anomali nol. Command read-only, memeriksa database MySQL dengan prefix `mgi_fresh_`, satu MGI aktif, kedua kolom, NULL owner, owner lain, dan Party berbeda company.
7. Tambahkan/ubah hanya flag A2 pada `.env` tanpa duplikasi key:

```dotenv
PLATFORM_ORDER_COMPANY_SCOPE_ENABLED=true
```

Pertahankan APP_KEY, DB_DATABASE, credential, session/cache/queue, `PLATFORM_LEGACY_SYNC_ENABLED=false`, dan `CEISA_ENABLED=false`. Pastikan DB_URL tidak mengalahkan database target.

8. Bersihkan config cache, verifikasi nilai **runtime**, jalankan readiness ulang:

```powershell
php artisan config:clear
php artisan platform:check-order-ownership
php artisan view:cache
php artisan view:clear
```

Hasil harus `READINESS=PASSED; scope=ON`. Gunakan bootstrap Laravel/Tinker untuk memverifikasi `SELECT DATABASE()`, flag, count/digest preserve, dan count bisnis masih nol. Jangan mencetak `.env` penuh/hash password.

9. Review bahwa hanya 2 migration A2 yang baru tercatat. COA, pengguna/password, grant Party, dan database BBW tetap sesuai snapshot. Session/log teknis boleh berubah; jangan mencampurkannya dengan transaksi bisnis.
10. Setelah semua pemeriksaan lulus, `php artisan up`. Bila menggunakan `artisan serve`, restart proses dev server agar source/config baru termuat; pertahankan URL lokal sebelumnya. Worker dan scheduler tetap berhenti.
11. Login menggunakan kredensial pengguna sendiri, pilih MGI di `/platform/company` jika diperlukan. Buka form PO/SO, cek hidden context dan branding login. Jangan membuat transaksi dummy pada database aktif. Pengujian create/edit/import/receive/ship dilakukan oleh suite atau clone fixture, tanpa meninggalkan transaksi pada MGI kerja.

Jangan mereset password atau memberi grant kepada user 1/2 demi smoke test. Party user 3 tetap satu-satunya grant yang telah disetujui. Jangan menjalankan `CompanyProfileSeeder`, migration fresh, seed seluruh database, fresh-start ulang, atau aktivasi sync/CEISA.

## 7. Bila gate gagal

Sebelum schema/aktivasi: source bisa diperbaiki dan diuji dalam staging. Selama migration/aktivasi gagal: pertahankan maintenance dan laporkan status masing-masing migration, koneksi serta flag; jangan otomatis menghapus kolom atau data.

Setelah PO/SO baru tersimpan dengan owner: jangan sekadar mematikan flag lalu membuka aplikasi, karena transaksi berikutnya dapat dibuat tanpa owner. Jangan rollback migration yang akan menghilangkan ownership. Utamakan perbaikan maju dengan backup. Restore hanya pada target yang diverifikasi setelah memperhitungkan transaksi baru; jangan mengalihkan aplikasi ke BBW.

## 8. Laporan penerimaan yang wajib

Laporkan file/hash installed dan deviasi lokal, hasil lint/targeted/full/MySQL tests aktual, database runtime, company/flags, migration yang dijalankan, readiness output, login/form smoke, count/digest preserve, kondisi transaksi, worker/scheduler, backup MGI, dan Git status. Pisahkan **terpasang**, **diuji**, dan **diaktifkan**; jangan menyatakan aktivasi selesai jika baru memasang source.

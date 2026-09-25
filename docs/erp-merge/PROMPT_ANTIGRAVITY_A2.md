# Prompt AI agent Antigravity — Pasang, uji, dan aktifkan A2 MGI

Anda bekerja langsung pada repository Windows:

```text
D:\xampp\htdocs\app-akuntansi-portfolio-master
```

Lanjutkan project ERP Marvel-Dard dari kondisi cutover MGI yang sudah selesai. Gunakan source dalam `ERP_Marvel_Dard_A2.zip`. Kerjakan sampai hasil konkret dapat diperiksa. Jangan hanya memberikan rencana. Baca instruksi repository yang berlaku, `docs/erp-merge/README_A2.md` dari staging paket, manifest A2, serta source yang relevan sebelum mengubahnya.

## Konteks yang harus diverifikasi

- Branch terakhir main; perubahan A1 + MGI belum di-commit. Jangan reset/clean/checkout yang menghilangkan working tree.
- Database aktif yang diharapkan `mgi_fresh_20260924`; BBW arsip `db_akuntansi` tetap utuh.
- Company ID 1, code MGI, PT. Magicase Group Indonesia, aktif.
- COA 208 akun; pengguna 3. Password, membership, dan role dipertahankan.
- Party grant view/create/update hanya untuk user ID 3 DARWIN, company 1; role PARTY_U3_C1. Jangan menambah grant user 1/2.
- Legacy sync false, CEISA false; worker/scheduler berhenti.
- APP_KEY dan credential tetap. Session/cache/queue menggunakan database MGI.
- Transaksi operasional dilaporkan kosong. Buktikan ulang; jangan menganggap laporan lama tetap benar jika user sudah memasukkan data.
- Profil resmi MGI selain nama belum diberikan; jangan menebak NPWP/alamat/PIN atau menjalankan CompanyProfileSeeder.

## Tujuan pekerjaan

Pasang ownership header PO/SO untuk satu perusahaan MGI dan branding login. Uji seluruh perubahan sebelum mengaktifkan flag A2. A2 tidak menjadikan seluruh ERP multi-company. Jangan menambah perusahaan kedua, mengaktifkan GRN/Payment Request/CEISA, mengubah COA/posting, atau mengulang fresh-start.

## Tahap 1 — Baseline dan pemasangan source

1. Periksa Git status/diff, runtime PHP, source baseline dan kondisi database secara read-only. Jangan mencetak secret.
2. Ekstrak arsip ke staging terpisah. Verifikasi integritas ZIP dan SHA-256 paket.
3. Verifikasi `baseline_sha256` semua file modified di `CHANGED_FILES_A2.json`. Periksa juga collision file added. Bila result hash sudah sama, tandai already applied. Bila hash berbeda, baca diff dan lakukan merge yang mempertahankan perubahan lokal; laporkan deviasi. Jangan overwrite buta.
4. Salin hanya file array `files`, ditambah `metadata_files`. Jangan timpa seluruh isi ZIP. Jangan mengganti `.env`, storage, vendor, database, atau arsip lokal dari paket.
5. Review patch, PHP lint, dan `git diff --check`. Perbaiki bila perlu tanpa memperluas scope ke fitur ERP lain. Jangan melakukan push atau staging seluruh working tree.

## Tahap 2 — Pengujian yang wajib lulus

Jalankan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1 -FullSuite
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a2.ps1 -MySqlIntegration
```

Paket belum diuji runtime di lingkungan pembuatnya. Jangan mengutip hasil test A1/MGI sebagai hasil A2. Catat test/asserasi aktual. Bila test gagal, diagnosis, perbaiki source/test sesuai perilaku yang benar, lalu jalankan ulang gate yang terdampak. Jangan mematikan middleware/scope atau melonggarkan assertion hanya supaya lulus.

Test biasa harus SQLite in-memory. Test MySQL harus database fixture acak `mgi_fresh_a2test_<16 hex>`; tidak boleh memakai database kerja. Pastikan fixture tidak tertinggal. Jangan menulis transaksi dummy pada MGI kerja. Jika perlu uji halaman/alur integrasi lebih luas, buat clone MGI terisolasi, jalankan aplikasi fixture dengan config terpisah, lalu bersihkan hanya fixture yang dibuat sendiri.

Perhatikan kasus: simpan/edit PO/SO; import/reimport PO; global scope pada OR/bulk query; company_id payload; context hilang/salah; revoked membership; company kedua; NULL owner; Party salah role; retur detail dari PO lain; receive/ship tanpa side effect ketika header tidak sah; invoice delete terkait SO tak sah; login config app.name.

## Tahap 3 — Aktivasi bertahap

Jika source, test, dan koneksi target sudah terbukti benar, lanjutkan prosedur aktivasi bagian 6 README_A2:

1. Maintenance, hentikan penulis aktif, backup MGI saat ini + `.env` di luar web root menggunakan nama timestamp. Backup BBW lama tetap dipertahankan. Verifikasi backup dengan restore ke fixture dan checksum.
2. Verifikasi PO/SO masih kosong. Jika sudah ada data, jangan hapus atau backfill; laporkan data tersebut dan tunda aktivasi ownership sampai pemilik datanya ditinjau.
3. Jalankan HANYA dua migration baru A2 dengan `--path` masing-masing. Jangan menjalankan migrate seluruh pending migration.
4. `php artisan platform:check-order-ownership` harus lulus dan tidak mengubah data.
5. Set hanya `PLATFORM_ORDER_COMPANY_SCOPE_ENABLED=true`, config:clear, verifikasi database + flag runtime, readiness ulang harus scope ON.
6. Compile/clear Blade; periksa migration yang terpasang, COA/user/grant serta count bisnis sebelum/sesudah. Jangan mengubah APP_KEY, credential, identitas DB, atau flag legacy/CEISA.
7. Jika seluruh gate lulus, `php artisan up`, jalankan kembali dev server lokal bila dibutuhkan. Worker/scheduler tetap berhenti.
8. Verifikasi login page, judul MGI, company picker, form PO/SO. Login interaktif hanya bila mekanisme kredensial user tersedia; jangan menebak/mencetak/reset password. Jika login manual masih perlu dilakukan user, nyatakan dengan jelas dan tetap selesaikan semua pemeriksaan otomatis yang tersedia.

Bila activation gate gagal setelah maintenance, jangan membuka aplikasi dengan keadaan parsial. Jelaskan kegagalan dan kondisi aktual. Setelah transaksi ber-owner ada, jangan mematikan scope lalu membuka aplikasi atau drop company_id; ini dapat menghasilkan data tanpa owner. Utamakan perbaikan maju.

## Larangan scope

- Tidak ada delete/truncate/reset database BBW atau MGI.
- Tidak ada migrate:fresh, fresh-start ulang, general seeding, atau menjalankan pending migration lain.
- Tidak mengubah formula nominal, journal posting, stock posting, master COA, pengguna/password, atau grant Party.
- Tidak mengaktifkan kembali legacy sync, CEISA, worker/scheduler.
- Tidak memasukkan profil perusahaan atau PIN yang ditebak.
- Tidak push Git, mengunggah backup, atau membocorkan credential.
- Tidak mengklaim dukungan multi-company penuh atau test lulus tanpa hasil eksekusi.

## Laporan akhir

Berikan ringkasan: file source/metadata terpasang, hash/deviations, lint + targeted/full/MySQL tests aktual, database/flags runtime, dua migration A2, output readiness, backup MGI, COA/user/grant tetap, count bisnis, login/form smoke yang benar-benar dilakukan, maintenance/dev server/worker/scheduler, Git status, dan batas yang belum diuji. Pisahkan status source terpasang, runtime diuji, dan flag diaktifkan.

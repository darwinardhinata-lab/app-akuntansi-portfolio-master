# Awal baru PT. Magicase Group Indonesia — pertahankan COA dan pengguna

## Status dan keputusan pengguna

Pengguna memilih mulai dari nol, dengan COA dan user tetap dipertahankan. Seluruh transaksi lama berasal dari BBW. Nama target: **PT. Magicase Group Indonesia**, kode internal yang dipilih: **MGI**.

Paket ini menyiapkan **database baru**, bukan menjalankan DELETE/TRUNCATE pada database BBW. Setelah aplikasi dialihkan, data operasional aktif mulai kosong. Database BBW dan backup tetap diarsipkan untuk pemulihan. Penghapusan fisik arsip tidak dilakukan.

A1 dilaporkan pengguna lulus 101 test/391 assertion di PHP 8.2.12, PHPUnit 11.5.55, Laravel 12. Perubahan MGI pada paket ini **belum diuji dengan PHP/MySQL di lingkungan pembuat paket**. Uji di lingkungan lokal wajib selesai sebelum cutover. Tidak ada akses dari sesi ini ke database XAMPP pengguna.

## Data target

| Data | Perlakuan |
|---|---|
| `accounts`, terjemahan akun/jenis COA | Disalin identik, termasuk kode dan klasifikasi; tidak mengubah COA |
| `users` | Disalin identik, termasuk ID, email, hash password dan role legacy |
| Role, permission, pivot akses user/company | Disalin identik; tidak memberi hak tambahan |
| Divisi | Hanya baris yang dirujuk `users.id_divisi`, agar user tidak kehilangan referensi |
| `migrations` | Disalin sebagai metadata schema; migration pending tetap pending |
| Company dan profile | ID dipertahankan untuk relasi akses; kode MGI/nama baru; alamat, NPWP, logo, kontak dan PIN lama dikosongkan |
| PO, SO, bill, invoice, jurnal, saldo awal, payment, GRN, produksi, customs | Kosong |
| Barang/stok, aset, Party/customer/supplier, rekening, cost center dan master operasional lain | Kosong |
| Queue, sesi, token API, cache, credential integrasi, audit transaksi lama | Kosong; histori lama tetap berada di arsip BBW |
| Tabel lain di database sumber | Schema disalin tetapi baris tidak dibawa, kecuali daftar eksplisit di atas |

Saldo awal baseline disimpan melalui jurnal. Mengosongkan jurnal pada target menghasilkan awal tanpa saldo transaksi. Tidak ada nominal saldo yang diubah dalam COA. Bila schema akun aktual punya kolom tambahan yang tidak dikenal, command berhenti untuk review.

Password tidak direset. Session database tidak dipindahkan. Jangan menganggap sesi/cache/queue Redis atau file ikut hilang: storage eksternal harus diisolasi ketika cutover.

## Isi paket dan cara merge

Source penuh tetap tersedia. Untuk repo A1 aktif, salin hanya file dari `CHANGED_FILES_MGI.json`, lalu **salin file metadata `CHANGED_FILES_MGI.json` itu sendiri**. Manifest tidak mencantumkan hash dirinya sendiri. Cocokkan baseline/result hash; jangan overwrite perubahan lokal yang berbeda.

Tidak ada migration atau perubahan `composer.lock`. Jangan menjalankan `migrate:fresh`, seluruh migration pending, `composer setup`, atau seluruh seeder.

## Tahap 1 — pengujian kode

1. Jalankan pada development dengan PHP >= 8.2, dependency dari lockfile, extension pdo_sqlite dan pdo_mysql.
2. Gunakan verifier baru:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-mgi-fresh-start.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-mgi-fresh-start.ps1 -FullSuite
```

3. Jalankan suite integrasi MySQL pada server TEST. Suite ini membuat dan menghapus hanya database fixture acak `mgi_test_source_<random>` dan `mgi_fresh_test_<random>`. Jangan mengisi kredensial server produksi untuk test. Contoh kredensial root tanpa password hanya untuk XAMPP test yang memang dikonfigurasi demikian:

```powershell
$env:MGI_TEST_HOST = '127.0.0.1'
$env:MGI_TEST_PORT = '3306'
$env:MGI_TEST_USER = 'root'
$env:MGI_TEST_PASSWORD = ''
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-mgi-fresh-start.ps1 -MySqlIntegration
```

Suite integrasi memeriksa dry-run tanpa target, copy COA/user identik, transaksi target kosong, identitas MGI, sumber BBW utuh, penolakan target existing, dan hash backup salah. Backup kecil dalam test hanya fixture untuk menguji gate hash, bukan bukti restore database.

## Tahap 2 — pratinjau terhadap database BBW

Pastikan konfigurasi aplikasi masih mengarah ke database BBW yang benar. Dari root repository:

```powershell
php artisan platform:prepare-mgi mgi_fresh_20260924
```

Tanpa `--apply`, command hanya membaca: tidak membuat database, tidak menyalin data, tidak mengubah `.env`.

Periksa nama sumber dan jumlah per tabel. Command menolak target existing, sumber multi-company, company selain BBW, profile tidak cocok, tabel non-InnoDB, views, triggers, routines, events, foreign key lintas database, dan schema COA/profile tambahan yang belum direview. Jika ditolak, kirim pesan dan metadata schema relevan; jangan melewati pemeriksaan.

## Tahap 3 — hentikan penulis dan backup

1. Jadwalkan cutover, jalankan `php artisan down` pada aplikasi sumber.
2. Hentikan worker queue, Windows Task Scheduler/cron, `schedule:work`, import eksternal dan proses lain yang menulis ke database. Maintenance web saja tidak menghentikan semua penulis.
3. Buat backup SQL penuh dengan mysqldump XAMPP. Ganti nama database/username sesuai konfigurasi yang sudah diverifikasi. Folder backup harus sudah ada dan tidak berada di public web root:

```powershell
& 'D:\xampp\mysql\bin\mysqldump.exe' --host=127.0.0.1 --user=USERNAME -p --single-transaction --routines --triggers --events --result-file='D:\ERP_Backup\BBW_sebelum_MGI.sql' NAMA_DATABASE_BBW
if ($LASTEXITCODE -ne 0) { throw 'Backup gagal; jangan lanjut.' }
Get-FileHash 'D:\ERP_Backup\BBW_sebelum_MGI.sql' -Algorithm SHA256
```

Gunakan prompt password; jangan menaruh password di command atau laporan. Uji restore backup pada database/server terpisah dan periksa isinya. Hash hanya membuktikan file cocok, bukan backup lengkap atau dapat dipulihkan. Arsipkan juga `.env` dan file unggahan dengan akses terbatas.

## Tahap 4 — siapkan target baru

Isi SHA-256 aktual, jangan memakai placeholder:

```powershell
php artisan platform:prepare-mgi mgi_fresh_20260924 --apply --workers-stopped --backup='D:\ERP_Backup\BBW_sebelum_MGI.sql' --backup-sha256=SHA256_AKTUAL
```

Command memerlukan hak CREATE DATABASE dan metadata schema. Source dibaca dalam snapshot read-only; struktur dibuat di target terpisah; data dipindahkan dalam transaction target. FK yang dinonaktifkan hanya pada koneksi target diperiksa kembali satu per satu sebelum commit. Record COA/user/akses diperiksa dengan digest, tanpa mencetak hash password atau isi pengguna.

Jika gagal setelah target dibuat, target parsial dibiarkan untuk diagnosis. Jangan mengalihkan aplikasi ke target tersebut. Perbaiki penyebab dan gunakan nama target baru, atau minta administrator meninjau target parsial. Tidak ada auto-drop database dan tidak ada penghapusan sumber.

## Tahap 5 — cutover setelah semua pemeriksaan lulus

1. Simpan `.env` lama. Isi konfigurasi dengan database target dan identitas baru:

```dotenv
APP_NAME="PT. Magicase Group Indonesia"
DB_DATABASE=mgi_fresh_20260924
PLATFORM_LEGACY_SYNC_ENABLED=false
CEISA_ENABLED=false
```

Jika `DB_URL` diisi, ubah atau hapus nilai itu secara terkontrol agar tidak mengalahkan `DB_DATABASE`. Periksa nama koneksi default yang sesungguhnya. Jangan mencetak credential.

2. Pisahkan cache/session/queue untuk aplikasi MGI. Untuk langkah awal, gunakan backend database target yang tabelnya sudah disalin kosong (`CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`) apabila baseline menyediakan tabel terkait. Jangan hubungkan MGI ke antrean Redis BBW. Jangan mulai ulang worker sebelum konfigurasi baru terverifikasi.
3. Pisahkan file unggahan lama dari storage publik aktif. Arsipkan file BBW; siapkan folder unggahan MGI kosong dan symlink publik yang sesuai. Command database tidak memindahkan atau menghapus file.
4. Jalankan `php artisan config:clear`; verifikasi nama database runtime melalui administrasi lokal. Periksa scheduler tetap tidak mendaftarkan job sinkronisasi dashboard. Flag false juga melindungi 11 handler job sync/ETL jika dipanggil langsung.
5. Jangan jalankan ulang import historis atau mengaktifkan credential CEISA lama. Import file manual tetap tersedia untuk data baru dan tidak dinonaktifkan oleh flag sync.
6. Verifikasi user dapat login dengan password lama, COA sama, nama MGI muncul, stok/transaksi/jurnal kosong, dan tidak ada job lama masuk.
7. Isi profil MGI yang benar: NPWP, alamat, kontak, logo dan PIN baru bila fitur pengajuan publik diperlukan. Data tersebut sengaja tidak ditebak.
8. Role/permission yang pernah diberikan tetap ada. Jika seeder/grant A1 belum dijalankan pada MySQL, katalog/hak Party masih perlu diberikan melalui prosedur A1; fresh start tidak menaikkan akses otomatis.
9. Setelah review lokal selesai, `php artisan up`. Mulai layanan background hanya sesuai konfigurasi MGI dan kebutuhan yang telah diverifikasi.

Jangan menjalankan `CompanyProfileSeeder` untuk memperoleh PIN default. Atur PIN baru melalui profil perusahaan jika fitur itu digunakan.

## Pemulihan

Selama belum ada transaksi MGI baru: hentikan penulis, kembalikan `.env`/storage/source yang sesuai ke BBW arsip, bersihkan config cache, dan verifikasi sebelum membuka aplikasi. Begitu MGI sudah menerima transaksi, pemulihan memerlukan rekonsiliasi data baru; jangan sekadar menghapus target atau kembali ke arsip.

## Verifikasi yang benar-benar dilakukan pada paket ini

- Pemeriksaan statis cakupan source, patch terhadap baseline A1, integritas arsip dan manifest.
- Tidak ada akses ke MySQL pengguna, eksekusi persiapan, perubahan `.env`, atau cutover.
- PHP lint, PHPUnit, pengujian MySQL, serta rendering Blade paket MGI belum dijalankan di lingkungan pembuat. Hasil A1 terdahulu tidak dianggap bukti bahwa command MGI sudah lulus.

## Sesudah fresh start

Mulai mengisi master operasional MGI yang diperlukan dan saldo awal baru hanya jika ada angka yang disetujui. GRN/Payment Request tetap belum diaktifkan. Kepemilikan company untuk transaksi baru dan aturan posting masih harus diterapkan pada tahap selanjutnya; fresh start bukan implementasi isolasi seluruh ERP.

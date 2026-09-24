# ERP Marvel-Dard — Paket A1: Company Context dan Master Party

Tanggal: 24 September 2026. Basis: `app-akuntansi-portfolio-master-main(1).zip` yang diunggah pengguna.

**Status: implementasi source + test tersedia; belum diuji dengan runtime PHP/Laravel dan belum dipasang pada database pengguna.**

## Hasil

- Menu baru **Perusahaan & Master Party**, URL `/platform/company`.
- Pemilihan company hanya dari membership aktif; default otomatis hanya jika tepat satu default aktif. Company yang dicabut/nonaktif tidak boleh dipakai, dan pilihan lama tidak otomatis diganti ke perusahaan lain.
- Master Party: daftar/pencarian/paginasi, tambah, edit, aktif/nonaktif, lima jenis role. Tidak ada hard-delete atau editor rekening bank pada paket ini.
- Scope company pada daftar, pencarian, detail edit, dan penyimpanan master Party. Data company dari payload dilarang; company ditetapkan server.
- Policy `party.view`, `party.create`, `party.update` melalui `user_role.company_id` dan `role_permission`. Role string ADMIN tidak memperoleh bypass otomatis.
- Form Party membawa company saat dibuka; submit dari tab lama setelah ganti company ditolak dengan HTTP 409.
- Audit create/update dan pemberian akses disimpan pada `system_logs`; tidak menyalin NPWP, alamat, nomor rekening atau isi data sensitif ke log.
- Dropdown dan validasi Party pada PO/SO sekarang hanya menerima Party aktif dengan role sesuai di company aktif. Nama manual tetap bisa dipakai.
- Party yang sudah tersimpan tetapi tidak lagi tersedia tetap ditampilkan sebagai pilihan peringatan pada form edit PO/SO, agar membuka form tidak otomatis menghapus linkage.
- Validasi Party dipindahkan sebelum transaction PO/SO agar error field tidak tertelan catch umum legacy.
- 26 test baru di `PlatformAccessTest`; 3 test Party linkage lama diperbarui agar memakai user/membership sebenarnya.

## Batas cakupan yang harus dipahami

**Paket ini belum mengisolasi seluruh transaksi ERP per company.** Pada migration source, PO/SO belum mempunyai `company_id`; produk, stok, bill, invoice, jurnal, warehouse, laporan, import/job, dan document trace masih mengikuti alur legacy. Mengganti company aktif hanya memengaruhi modul Party dan pemilihan Party pada form manual PO/SO.

Company milik Party tidak digunakan untuk menebak pemilik dokumen historis. Pengguna yang memiliki akses legacy PO/SO tetap dapat melihat dokumen sesuai perilaku legacy. Jangan mengaktifkan pemisahan pelanggan/tenant produksi dengan menganggap paket ini sudah menjadi isolasi seluruh aplikasi.

Izin Party master tidak menggantikan hak transaksi PO/SO: pemilihan Party oleh user yang boleh mengakses form PO/SO legacy dibatasi membership company dan role Party, sedangkan CRUD Party memakai policy baru.

Tidak ada migration baru, perubahan COA, perubahan nominal/posting, backfill, aktivasi GRN/Payment Request, atau akses ke database lokal XAMPP pengguna. Tabel platform harus sudah terpasang sebagaimana baseline.

## Cara memasang di development/staging

1. Simpan perubahan lokal/backup dan gunakan branch kerja. Cocokkan baseline dengan manifest SHA-256 dalam paket; jangan overwrite file yang lebih baru dari ZIP sumber.
2. Gunakan source lengkap hasil revisi, atau ambil hanya file dalam `CHANGED_FILES.json`. File lama di luar daftar tetap dipertahankan.
3. Gunakan PHP >= 8.2, dependency sesuai `composer.lock`, dan database development yang terpisah. Jika dependency belum tersedia: `composer install --no-scripts`, lalu `php artisan package:discover`. Jangan memakai script `composer setup` karena script baseline menjalankan migration secara umum.
4. Verifikasi migration platform yang sudah ada melalui `php artisan migrate:status`. Paket A1 tidak membutuhkan `php artisan migrate`; jangan menjalankan migration Customs Reports yang pending tanpa scope terpisah.
5. Bersihkan cache development setelah file digabung: `php artisan optimize:clear`.
6. Isi katalog permission (idempoten, tidak memberikan akses):

```powershell
php artisan db:seed --class=PlatformPermissionSeeder
```

7. Operator yang berwenang menetapkan akses untuk ID user/company yang benar dan membership yang sudah tersedia. Angka di bawah adalah contoh; ganti dengan ID yang diverifikasi:

```powershell
php artisan platform:party-access 7 2 --allow=view --allow=create --allow=update
```

Perintah membuat role khusus untuk pasangan user/company, bukan mengubah role ADMIN/FINANCE/STAFF bersama. Akses baru tidak diberikan otomatis. Perintah menolak company nonaktif/non-member dan menolak mengubah role khusus yang ternyata digunakan pasangan lain.

Untuk melihat saja gunakan `--allow=view`. Untuk menghapus grant khusus itu, jalankan dengan user/company yang sama tanpa opsi `--allow`. Izin dari role lain tetap berlaku dan harus diperiksa saat mencabut seluruh akses. Seeder katalog sendiri tidak mencabut atau menambah assignment.

8. Login, buka **Perusahaan & Master Party**, pilih company, lalu buka Daftar Party/Tambah Party. Jika tidak ada tombol, periksa permission pasangan user/company tersebut.
9. Jalankan verifikasi pada database test disposable dengan `tools/verify-platform-a1.ps1`. Script mengatur environment proses ke SQLite in-memory dan menghindari cache config normal. Script tidak mengubah `.env` atau menjalankan migration terhadap database kerja. Pastikan extension SQLite tersedia. Setelah test terarah lulus, jalankan script dengan `-FullSuite`.
10. Verifikasi visual create/edit/list, pencarian, company switch, penolakan akses asing, dan form PO/SO di browser staging.

## Validasi dalam lingkungan pembuat paket

- Source baseline dan perubahan ditinjau statis; referensi route/class dan cakupan file diperiksa.
- Patch diperiksa terhadap arsip baseline; file jurnal, stok, COA, migration, dan dependency lockfile tidak dimodifikasi.
- PHP CLI, Composer, dan `vendor/autoload.php` tidak tersedia. Karena itu **php -l, PHPUnit, Blade compilation, request Laravel, dan uji database tidak dijalankan**. Tidak ada klaim 101 test lulus atau angka assertion baru.
- Angka historis 75 test/296 assertion berasal dari catatan pengguna, bukan eksekusi pada paket ini.
- Pengujian concurrency, browser, MySQL, serta rekonsiliasi produksi belum dilakukan.

## Pemulihan

A1 tidak mengubah schema. Untuk rollback source gunakan baseline pengguna, dengan mempertahankan perubahan lain di branch. Data Party yang dibuat tetap tersimpan dan kompatibel dengan model baseline. Tinjau perubahan master dan grant yang sempat dibuat; rollback source tidak mengembalikan isinya. Jangan menghapus Party atau tabel platform sebagai cara rollback.

Mengembalikan controller PO/SO lama akan mengembalikan daftar Party tanpa pembatasan company. Pertimbangkan dampak tersebut sebelum rollback.

## Urutan berikutnya

1. Jalankan test A1 dan review hasil di runtime lokal.
2. Putuskan ownership company transaksi legacy, termasuk dokumen tanpa Party, kemudian siapkan migration nullable, dry-run mapping dan daftar ambigu. Kepemilikan harus mencakup semua entrypoint yang menggunakan dokumen, bukan hanya controller PO/SO.
3. Tetapkan waktu pengakuan GRN/Bill, mapping COA yang disetujui, matriks approval, rekening sumber, dan arti POSTED Payment Request.
4. Implementasikan alur baru berikut atomicity, idempotensi, reversal, dan rekonsiliasi. Detail ada di `DESAIN_TRANSISI_GRN_PAYMENT.md`.

# Pengaman Database dan Pemulihan Jurnal MGI

Database operasional yang dituju aplikasi adalah `mgi_fresh_20260924`. Database
`db_akuntansi` adalah arsip/legacy dan **bukan** target restore tanpa keputusan
bisnis yang terdokumentasi.

## Larangan utama

- Jangan menjalankan `migrate:fresh`, `migrate:refresh`, `migrate:reset`,
  `migrate:rollback`, atau `db:wipe` pada database `mgi_fresh_*`.
- Guard source-level memblokir command tersebut di luar environment `testing`.
- `ALLOW_DESTRUCTIVE_MGI_DATABASE_COMMANDS=true` adalah escape hatch recovery
  yang sangat terbatas, bukan solusi masalah data. Nilainya harus dikembalikan
  ke `false` segera setelah prosedur recovery yang disetujui selesai.
- Jangan mengimpor dump SQL langsung ke database operasional sebelum langkah
  preflight dan backup di bawah ini selesai.

## Prosedur wajib sebelum restore manual

1. Hentikan penulis aktif: pengguna, queue worker, scheduler, dan integrasi.
2. Pastikan target dengan `php artisan about` dan verifikasi nama database adalah
   `mgi_fresh_20260924`.
3. Buat dump baru database target di luar web root menggunakan timestamp, lalu
   catat checksum SHA-256. Jangan menimpa dump lama.
4. Jalankan preflight **read-only** pada dump sumber:

   ```powershell
   php artisan recovery:verify-dump "D:\path\sumber.sql" --require-journals
   ```

   Command ini hanya memindai teks SQL; tidak menjalankan statement SQL dan
   tidak mengubah database. Ia memerlukan struktur dan data `accounts`,
   `journal_headers`, serta `journal_details`.

5. Jika preflight gagal, **berhenti**. Cari dump lain; jangan restore dump yang
   hanya memiliki struktur tabel kosong. Sebagai contoh, dump
   `MGI_PRE_M1_COA_20260928_120619` ditolak untuk pemulihan jurnal karena tidak
   mempunyai baris `journal_headers` maupun `journal_details`.
6. Restore dahulu ke database fixture terpisah, bukan target operasional.
   Bandingkan jumlah tabel/baris dan jalankan audit berikut pada fixture.
7. Hanya setelah review operator, lakukan restore manual yang disetujui, lalu
   jalankan audit pasca-restore:

   ```powershell
   php artisan accounting:check-integrity --require-journals
   php artisan journal:check-duplicates
   php artisan jurnal:check-balance 2020-01-01 2100-12-31
   ```

8. Audit harus lulus: tidak ada header tanpa detail, detail tanpa header, atau
   jurnal Debet/Kredit tidak balance. Simpan output audit bersama backup.
9. Nyalakan kembali penulis aktif hanya setelah acceptance test login, buku
   besar, laba-rugi, dan neraca.

## Pemantauan rutin

Jalankan `php artisan accounting:check-integrity` sebelum dan sesudah pekerjaan
schema/migrasi, serta setelah import transaksi besar. Command ini read-only dan
aman dijalankan pada database operasional.
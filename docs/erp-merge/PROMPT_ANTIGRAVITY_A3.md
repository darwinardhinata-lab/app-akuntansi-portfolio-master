# Prompt Antigravity — A3 GRN MGI

Lanjutkan pekerjaan pada `D:\xampp\htdocs\app-akuntansi-portfolio-master` dari baseline source A2 Activated. Pasang dan uji paket `ERP_Marvel_Dard_A3.zip`, lalu lakukan aktivasi terkontrol hanya jika seluruh gate dan batas penggunaan README terpenuhi. Kerjakan hasil konkret, bukan sekadar rencana. Baca AGENTS.md/instruksi repository bila ada.

## Baseline dan batas yang wajib dipertahankan

- Source baseline ZIP SHA-256 `020D5346B0B51E313F84F1B9C688DF92EDD7E8A4E2A9F1ED217A6B17929F410F`; ketiga perbaikan A2 sudah masuk. Jangan kembalikan SalesOrderController atau down() migration A2 ke paket lama.
- Database diharapkan `mgi_fresh_20260924`, company1/MGI, A2 scope ON. Verifikasi aktual melalui Laravel, bukan hanya `.env`.
- COA208/users3/password tetap; Party grant hanya user3/DARWIN. Legacy sync/CEISA false; worker/scheduler berhenti. BBW `db_akuntansi` tetap arsip.
- Tidak membuat dummy transaksi pada MGI, tidak reset/truncate/backfill, tidak mengubah COA/jurnal mapping, user/grant, APP_KEY, credential, atau profil resmi.
- A3 V1 hanya pembelian hutang tanpa uang muka/pajak/diskon, satu receipt ke satu Bill baru. Reversal/approval/tutup periode baru dan alokasi banyak GRN ke satu Bill belum dibuat. Jangan menebak aturan akuntansi atau membuat nomor faktur palsu demi lolos.

## 1. Review dan pemasangan source

Baca `docs/erp-merge/README_A3.md`, `CHANGED_FILES_A3.json`, service GRN dan test dari staging. Periksa Git status dan diff tanpa reset/clean/checkout. Bandingkan hash baseline semua file modified dan collision file added. Result hash sama berarti already applied. Perbedaan lokal harus direview/merge serta dicatat, bukan ditimpa.

Salin hanya file array `files` + `metadata_files`, termasuk manifest. Jangan overwrite seluruh ZIP. Source lengkap hanya referensi. Jangan menyalin atau mengganti `.env`, storage, vendor, bootstrap/cache, backup atau database dari ZIP. Alternatif patch hanya dipakai setelah `git apply --check`, jangan digabung dengan copy ulang.

## 2. Uji runtime sebelum aktivasi

Paket belum diuji PHP/MySQL oleh pembuatnya. Jalankan ketiga gate:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1 -FullSuite
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1 -MySqlIntegration
```

Test biasa SQLite in-memory. MySQL memakai fixture baru `mgi_fresh_a3test_<16 hex>`, migration chain nyata, dan dua child process khusus concurrency. Jangan jalankan test pada database kerja. Pastikan fixture/proses selesai dibersihkan; tidak ada penulis yang tertinggal. Jangan memublikasikan credential test.

Bila lint/test gagal, diagnosis dan perbaiki akar masalah, pertahankan tujuan/ketatnya assertion, lalu ulang gate terdampak. Jangan mematikan FK/scope, menghapus guard, mengganti database ke MGI, atau mencatat test sebagai lulus jika belum dieksekusi. Laporkan deviasi hash secara rinci. Full suite harus tetap lulus bersama A1/A2/MGI.

Review khusus: 40+60, overreceipt simultan, same-key retry sesudah PO selesai, konflik payload, Bill yang sudah ada, SKU hilang, rollback sesudah posting, jumlah/debit/kredit sama dengan legacy, durable route saat flag off, import PO, overwrite jurnal, Bill delete, dan generic stock sync dengan evidence GRN.

## 3. Deployment setelah gate

Ikuti bagian Aktivasi README_A3 secara berurutan. Bila kebutuhan operasi langsung ternyata meliputi pajak/uang muka/satu faktur banyak GRN, selesaikan pemasangan+testing dan biarkan flag OFF; laporkan batas itu. Jangan mengubah policy sendiri.

Jika gate/batas terpenuhi, verifikasi DB actual dan snapshot preserve; maintenance; hentikan penulis; backup MGI + `.env` baru berlabel PRE_A3 di `D:\ERP_Backup`; hash dan restore-test. Verifikasi transaksi masih kosong sebelum aktivasi pertama. Jika tidak kosong, jangan hapus/backfill; lakukan review dan laporkan.

Jalankan hanya:

```powershell
php artisan migrate --path=database/migrations/2026_09_25_090001_add_grn_posting_contract.php --force
php artisan platform:check-order-ownership
php artisan platform:check-grn
```

Jangan `migrate` semua pending atau `migrate:fresh`. Jika DDL gagal sebagian, maintenance tetap aktif dan identifikasi kondisi schema, jangan drop otomatis.

Set hanya `PLATFORM_GRN_ENABLED=true` setelah readiness lulus. Config/view clear, readiness ulang harus flag ON, compile/clear Blade, cek runtime MGI dan preserve COA/user/grant. Transaksi kerja tetap kosong. `php artisan up`, restart dev server bila perlu; worker/scheduler tetap berhenti. Smoke halaman login/GRN/PO/gudang/Bill secara read-only. Gunakan login user yang tersedia secara sah; jangan menebak atau reset password. Bila login interaktif belum tersedia, selesaikan semua pemeriksaan otomatis dan jelaskan yang belum dilakukan.

## 4. Laporan akhir

Pisahkan **source terpasang**, **runtime diuji**, **migration terpasang**, dan **flag aktif**. Laporkan:

- jumlah file, hash dan setiap perbaikan lokal;
- PHP lint, targeted/full/MySQL aktual dengan test/assertion, termasuk hasil concurrency;
- fixture/proses tersisa, hasil `git diff --check`, status Git (tanpa push/staging seluruh repo);
- backup PRE_A3, hash, hasil restore dan kondisi maintenance;
- database/company/flag runtime, migration A3 saja, output readiness;
- count/digest COA/user/grant dan count bisnis sebelum/sesudah;
- halaman yang benar-benar diperiksa serta batas yang belum diuji;
- seluruh batas V1 yang tetap berlaku.

Jangan menghapus GRN posted atau jurnalnya untuk koreksi. Jangan mematikan A2, membuka aplikasi dengan schema parsial, atau mengulang fresh-start. Jika pekerjaan tertahan gate konkret, jelaskan gate, kondisi actual, dan tindakan yang sudah selesai.

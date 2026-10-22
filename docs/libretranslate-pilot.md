# Pilot LibreTranslate lokal — Jurnal Umum dan Buku Besar

## Aktivasi operasional terbatas — 21 Oktober 2026

- Backup database penuh (450804 byte), `.env` sebelum aktivasi, dan snapshot jurnal disimpan di luar web root: `D:\xampp\private-backups\libretranslate-20261021-033856`. Lindungi folder ini karena berisi data keuangan dan kredensial konfigurasi.
- SHA-256 dump: `a5c9d54b785f530a7680088b82b389def1d5b888054213fcb3b979e7cb6700d5`. Dump berhasil dibuat; pemulihan ke database terpisah belum diuji.
- Hanya migrasi `2026_10_21_110000_create_journal_note_translations.php` yang dijalankan. Tabel `jobs` dan `failed_jobs` sudah tersedia.
- `.env` lokal sudah diaktifkan ke `http://127.0.0.1:5001`, target Mandarin `zh-Hans`, koneksi antrean `translations`.
- Jurnal `JRN-20261020-000008` berhasil diproses melalui worker `--once --tries=1`: dua hasil tersimpan dan pemilihan tampilan locale diverifikasi. Seluruh field header/detail cocok dengan snapshot sebelum proses.
- Antrean terjemahan tersisa 0; failed job terjemahan 0 saat pemeriksaan. Worker satu kali sudah berhenti, tidak ada worker permanen yang dibuat oleh aktivasi ini.
- **Flag aktif berlaku untuk semua penyimpanan jurnal melalui observer, bukan whitelist satu jurnal.** Jurnal baru/perubahan catatan dapat mengantre tetapi memerlukan worker untuk diproses. Seluruh histori tidak dijadwalkan. Nonaktifkan flag jika pengantrean otomatis belum diinginkan.
- Hasil lengkap pilot disimpan privat pada `pilot-verification.json` di folder backup. Kualitas bahasa dan hasil visual PDF/Excel belum disetujui reviewer.
- Regresi: 21 tes, 464 assertion berhasil; pemeriksaan diff bersih.

Bagian berikut menjelaskan default kode dan prosedur instalasi ulang; berbeda dengan status `.env` lokal yang kini aktif di atas.

## Status dan batasan

Fitur default **nonaktif**. Tidak menggunakan Azure/Google atau instance publik. URL pilot hanya menerima `127.0.0.1` / `[::1]`; redirect tidak diikuti. Keterangan jurnal Indonesia diasumsikan sebagai sumber; input campuran perlu pemeriksaan manual. `zh_CN` dipetakan ke `zh-Hans`, sesuai hasil `/languages` image pilot. Untuk versi layanan yang masih memakai `zh`, atur `LIBRETRANSLATE_CHINESE_TARGET=zh` dan periksa ulang `--check`. Indonesia → Mandarin dapat melalui pivot Inggris.

Pilot mencakup catatan header jurnal pada daftar/detail jurnal, Buku Besar, print/PDF berbasis tampilan tersebut, dan Excel kedua menu. Form edit tetap sumber asli. Bukan penerjemahan detail jurnal, semua menu, COA, atau seluruh histori. Teks asli dapat dilihat melalui tooltip pada daftar. Tidak ada koreksi manual/glosarium atau jaminan preservasi nama/nomor di dalam teks bebas: periksa hasil sebelum dokumen resmi digunakan. PDF Mandarin perlu uji font dan tata letak di browser.

## 1. Prasyarat dan jalankan layanan

Pasang Docker Desktop dengan Linux containers/WSL2 sesuai kebijakan perangkat. Container versi v1.9.6 sudah berhasil dijalankan pada port lokal 5001; port 5000 digunakan container lain dan tidak diubah. Docker Desktop mempunyai ketentuan lisensi sendiri; cek kelayakan penggunaan gratis organisasi. LibreTranslate AGPL-3.0; tinjau kewajiban bila memodifikasi/menyediakan layanan. Kelayakan kualitas bahasa tetap memerlukan pemeriksaan manusia.

Jalankan PowerShell:

```powershell
Set-Location 'D:\xampp\htdocs\app-akuntansi-portfolio-master'
docker compose -f 'D:\xampp\htdocs\app-akuntansi-portfolio-master\compose.libretranslate.yaml' config
docker compose -f 'D:\xampp\htdocs\app-akuntansi-portfolio-master\compose.libretranslate.yaml' up -d
docker compose -f 'D:\xampp\htdocs\app-akuntansi-portfolio-master\compose.libretranslate.yaml' logs --tail 100
& 'D:\xampp\php\php.exe' artisan translation:journal --check
```

Compose mematok versi `v1.9.6`, hanya bind loopback, dan menyimpan model pada named volume. Pengunduhan pertama perlu internet dan bisa lama; setelah model tersedia inference berjalan lokal. Tidak mengirim catatan ke situs publik. Koneksi keluar container belum diblokir oleh firewall; untuk kebijakan offline ketat terapkan pembatasan setelah instalasi model. Proses lokal lain masih dapat mengakses port tanpa API key: gunakan hanya pada host tepercaya, jangan ubah bind menjadi publik.

## 2. Persiapkan schema (backup database terlebih dahulu)

### Hasil smoke test lokal — 21 Oktober 2026

- Container `libretranslate/libretranslate:v1.9.6` healthy pada `127.0.0.1:5001`; container lain `mgi-translator` pada port 5000 tidak diubah.
- `translation:journal --check` berhasil; bahasa `id`, `en`, `zh-Hans` tersedia.
- Contoh sintetis: `Pembayaran sewa gudang Oktober 2026.`
- Inggris: `The rent payment for the October 2026 warehouse.` (sekitar 1,18 detik; susunan kalimat kurang alami dan perlu review).
- Mandarin: `2026年10月仓库租金支付.` (sekitar 0,95 detik; belum disetujui reviewer Mandarin).
- Respons Mandarin dikonfirmasi melalui pembacaan UTF-8 mentah; PowerShell lama dapat salah mendekode respons pada tampilan `Invoke-RestMethod`.
- Snapshot RAM container setelah permintaan: sekitar 337 MiB; bukan pengukuran beban produksi/peak.
- 21 pengujian otomatis terkait berhasil, 464 assertion. Tidak ada data transaksi nyata yang dikirim pada smoke test, tidak menjalankan migrasi operasional atau mengaktifkan fitur di `.env`.
- Image mengeluarkan warning dependency Python dan deteksi GPU; endpoint sehat pada pengujian ini. Pantau log sebelum penggunaan jangka panjang.

Migrasi ini tidak dijalankan otomatis oleh pekerjaan implementasi:

```powershell
& 'D:\xampp\php\php.exe' artisan migrate --path=database/migrations/2026_10_21_110000_create_journal_note_translations.php
```

Pastikan tabel `jobs` dan `failed_jobs` bawaan aplikasi tersedia. Jangan gunakan `migrate:fresh`, `db:wipe`, atau rollback massal pada database operasional. Jika tabel terjemahan dari eksperimen lain sudah ada, tinjau schema terlebih dahulu, jangan hapus sembarang data.

## 3. Aktifkan melalui .env lokal

Edit `D:\xampp\htdocs\app-akuntansi-portfolio-master\.env`:

```dotenv
LIBRETRANSLATE_ENABLED=true
LIBRETRANSLATE_URL=http://127.0.0.1:5001
LIBRETRANSLATE_SOURCE=id
LIBRETRANSLATE_CHINESE_TARGET=zh-Hans
LIBRETRANSLATE_MODEL_VERSION=pilot-1
LIBRETRANSLATE_QUEUE_CONNECTION=translations
```

```powershell
& 'D:\xampp\php\php.exe' artisan config:clear
& 'D:\xampp\php\php.exe' artisan queue:work translations --queue=translations --timeout=150 --tries=3 --sleep=3
```

Gunakan satu worker dahulu. Koneksi antrean khusus mempunyai `retry_after=240`, lebih lama dari timeout job 150 detik. Jangan gunakan `sync` untuk terjemahan. Setelah perubahan konfigurasi, restart worker. Antrean/default worker ERP lain tidak perlu diubah.

Jurnal baru/perubahan `notes` lewat Eloquent dijadwalkan setelah commit. Gagal dispatch tidak membatalkan jurnal, tetapi perlu dijadwalkan ulang lewat perintah di bawah. Bulk insert/update SQL melewati observer: gunakan perintah eksplisit untuk jurnal pilot tersebut.

## 4. Uji satu jurnal terlebih dahulu

```powershell
& 'D:\xampp\php\php.exe' artisan translation:journal 'ID-JURNAL-YANG-SUDAH-ADA'
& 'D:\xampp\php\php.exe' artisan queue:failed
```

Argumen di atas harus diganti dengan ID jurnal yang diizinkan. Perintah ini menjalankan satu jurnal saja, bukan seluruh histori. Gagal sementara dicoba ulang maksimal tiga kali; periksa worker/failed jobs lalu jadwalkan ulang satu jurnal. API tidak dipanggil dari proses membuka halaman/export.

Pilih bahasa Inggris/Mandarin pada aplikasi setelah job selesai. Periksa:
- Teks asli pada form edit tidak berubah; debit/kredit/saldo sama.
- Catatan daftar/detail, Buku Besar, print/PDF, dan Excel konsisten.
- Setelah teks asli diedit, hasil lama tidak dipakai. Teks asli tampil sampai hasil terbaru tersedia.
- Uji angka, invoice, nama perusahaan, negasi, DP, pelunasan, retur dan 50–100 sampel tersamarkan; hasil perlu persetujuan pembaca Inggris/Mandarin.
- Catat CPU/RAM, waktu startup/inference dan dampak terhadap MySQL sebelum memperluas pilot.

Cache disimpan per jurnal/bahasa/hash sumber+konfigurasi model. Hanya hasil berhasil disimpan. Tidak mengirim ulang hasil yang sudah tersedia. Hasil lama bisa tetap tersimpan tetapi tidak ditampilkan untuk versi baru. Ubah `LIBRETRANSLATE_MODEL_VERSION` bila model/kebijakan berubah. Catatan kosong/lebih dari 5000 karakter dilewati, sumber tetap tampil. Tidak ada UI status pending; sumber tampil bila terjemahan belum ada. Terjemahan mengikuti hak akses jurnal, tidak ada endpoint publik baru.

## 5. Nonaktifkan

Set `LIBRETRANSLATE_ENABLED=false`, clear config, restart/hentikan worker terjemahan. Job nonaktif tidak memanggil API. Data asli tetap utuh; tabel cache tidak perlu dihapus.

```powershell
docker compose -f 'D:\xampp\htdocs\app-akuntansi-portfolio-master\compose.libretranslate.yaml' stop
```

Jangan gunakan `down -v` kecuali memang ingin menghapus model tersimpan. Tidak ada biaya API per karakter, tetapi listrik/server/pemeliharaan tetap ada.

## Sumber
- https://docs.libretranslate.com/guides/installation/
- https://docs.libretranslate.com/guides/api_usage/
- https://docs.libretranslate.com/guides/supported_languages/
- https://github.com/LibreTranslate/LibreTranslate/releases/tag/v1.9.6
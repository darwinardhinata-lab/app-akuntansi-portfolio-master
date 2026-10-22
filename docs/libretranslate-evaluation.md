# Evaluasi LibreTranslate dan rollback pilot Azure

## Keputusan

LibreTranslate layak untuk pilot self-hosted tanpa biaya API per karakter.
Belum dipasang atau diintegrasikan: kualitas keterangan akuntansi belum diuji.
Integrasi pilot Azure dicabut dari kode; perbaikan saldo dan katalog bahasa tetap ada.
Keterangan kembali ditampilkan dalam bahasa aslinya pada jurnal, buku besar dan export.
Integrasi DeepL COA yang sudah ada tidak diubah.

## Temuan dari sumber resmi

- Mesin penerjemah lokal memakai Argos Translate, bukan Azure/Google.
- Model `id -> en`, `en -> id`, `en -> zh`, `zh -> en` tersedia pada indeks resmi.
- `zh` dibedakan dari `zt` (Chinese traditional). Verifikasi hasil simplified pada pilot.
- Tidak ditemukan model langsung `id -> zh` pada indeks yang diperiksa.
  LibreTranslate otomatis memakai pivot (biasanya Inggris) ketika pasangan langsung
  tidak tersedia. Ini menambah risiko perubahan makna dan waktu proses.
- Self-hosting tidak memerlukan biaya lisensi API per karakter, tetapi membutuhkan
  mesin, listrik, ruang model, backup, pemeliharaan, dan kapasitas CPU/RAM.
  Tidak ada jaminan biaya infrastruktur nol atau layanan tersedia selamanya.
- Endpoint publik libretranslate.com memerlukan pembelian API key untuk produksi;
  open source tidak berarti endpoint publik gratis tanpa batas.
- Lisensi proyek AGPL-3.0. Tinjau kewajiban distribusi dan penyediaan source untuk
  modifikasi layanan yang diakses melalui jaringan. Menjalankan layanan terpisah
  melalui HTTP bukan dasar untuk menyimpulkan seluruh ERP otomatis wajib AGPL;
  kebutuhan kepatuhan bergantung cara integrasi/distribusi dan perlu ditinjau.

## Rancangan pilot berikutnya (belum diimplementasikan)

1. Jalankan layanan terpisah dari PHP/XAMPP. Docker belum terdeteksi di PATH saat
   evaluasi; Python launcher yang terdeteksi belum membuktikan runtime siap.
2. Gunakan versi image yang dipatok; muat hanya bahasa `id,en,zh` dan persist model.
   Batasi port ke loopback/private network, bukan internet publik. Gunakan
   autentikasi dan TLS bila melewati jaringan; jangan memakai instance publik acak.
3. Periksa `/health` dan `/languages` pada instance yang benar-benar dipasang.
4. Uji 50–100 keterangan tersamarkan ke Inggris dan Mandarin; periksa angka,
   nomor dokumen, nama pihak, negasi, istilah akuntansi dan latensi. Reviewer
   Mandarin perlu memeriksa hasil, termasuk efek pivot bahasa.
5. Integrasi Laravel melalui HTTP client yang sudah tersedia, tanpa library PHP
   tambahan: POST `/translate`, body `q`, `source`, `target`, `format: text`,
   dan `api_key` bila instance memerlukannya; validasi `translatedText`.
6. Tetap gunakan antrean setelah commit, hasil tersimpan per versi sumber,
   fallback teks asli, izin dokumen sumber, timeout dan batas beban.
   Print/export tidak boleh menerjemahkan ulang setiap kali dibuka.
7. Ukur pemakaian CPU/RAM pada hardware aktual sebelum menentukan kapasitas.
   Angka minimum RAM universal tidak ditetapkan dalam evaluasi ini.

## Batas rollback

Tidak menjalankan `migrate:rollback`, drop tabel, menghapus antrean atau mengubah
database operasional. Pada implementasi sebelumnya migrasi belum dijalankan;
jika operator kemudian menjalankannya sendiri, tabel tambahan mungkin masih ada.
Periksa status migrasi dan job dengan backup sebelum pembersihan terarah.
Jangan menggunakan rollback batch umum yang dapat membatalkan migrasi lain.
Tidak ada variabel `AZURE_TRANSLATOR_*` pada `.env` ketika diperiksa; contoh
konfigurasi Azure di `.env.example` dihapus. Worker lama yang sudah hidup harus
dihentikan/restart oleh operator; menghapus kode tidak menghentikan proses hidup.

## Validasi kode setelah pencabutan integrasi

Pengujian `LedgerPageTotalsTest`, `LedgerExportTest`, dan
`AccountingOutputTranslationTest` berhasil: 8 tests, 427 assertions.
Pengujian ini memeriksa regresi laporan, bukan kualitas mesin LibreTranslate.
Belum ada layanan LibreTranslate lokal yang dipasang atau diuji langsung.

## Referensi

- https://github.com/LibreTranslate/LibreTranslate
- https://docs.libretranslate.com/guides/supported_languages/
- https://raw.githubusercontent.com/argosopentech/argospm-index/main/index.json
- https://docs.libretranslate.com/guides/installation/
- https://docs.libretranslate.com/guides/faq/
- https://docs.libretranslate.com/guides/api_usage/
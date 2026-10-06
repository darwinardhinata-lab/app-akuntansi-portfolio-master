# Struktur sidebar

## Pengelompokan

- Menu Utama: Dashboard.
- Operasional: Penjualan (SO, faktur, retur), Pembelian (PO, GRN, retur),
  Gudang & Persediaan (proses pesanan, barang masuk/keluar), Manufaktur.
- Manufaktur: perencanaan/produksi, pengadaan/stok bahan, laporan, master bahan.
- Keuangan: master akun/kode bantu/pajak/kategori pembayaran, budgeting,
  jurnal, AP (tagihan, Payment Plan, laporan AP), AR, aset, laporan keuangan.
- Kepatuhan: dokumen Bea Cukai, laporan CEISA, tautan eksternal INSW.
- Pengaturan: master barang/divisi; perusahaan, master party, profil perusahaan,
  manajemen pengguna dan log aktivitas.

Route dan ID collapse existing dipertahankan. Link administrasi pengguna/log
tetap khusus role ADMIN; perusahaan/platform tetap memerlukan autentikasi.
GRN tetap mengikuti company-scope flag. Customs tetap mengikuti flag modul
dan keberadaan route. Tidak ada perubahan RBAC backend, database atau posting.

## Tampilan dan perilaku

- Lebar sidebar 292px dengan indentasi ringkas dan label panjang yang membungkus.
- CSS link navigasi dibatasi ke sidebar agar tidak mengubah tab halaman.
- Ikon konsisten, warna aktif/fokus jelas, dukungan tema terang/gelap dan
  reduced motion. Drawer mobile, overlay dan bottom navigation dipertahankan.
- Status section tersimpan tidak boleh menutup section halaman aktif.
- Event collapse submenu tidak boleh mengubah status parent melalui bubbling.
- AR/AP dan manufaktur/CEISA menandai link laporan yang sesuai.

## Validasi

### Jika menu Bea Cukai tidak muncul

- Untuk laporan manual, set `CUSTOMS_REPORTS_ENABLED=true` pada `.env`.
  Menu Bea Cukai, laporan CEISA dan INSW tersedia tanpa mengaktifkan H2H.
- `CEISA_ENABLED=true` mengaktifkan dokumen/integrasi H2H dan laporan untuk
  kompatibilitas instalasi lama. Jangan aktifkan H2H pada company-scope A2/GRN:
  guard operasional akan memblokir SO/PO karena H2H belum mendukung ownership A2.
- Pastikan empat migrasi `2026_09_22_08000*` tabel `cbr_` sudah dijalankan.
  Jika status masih `Pending` tetapi tabel sudah ada, jangan hapus tabel atau
  menjalankan `migrate:fresh`: periksa kesesuaian schema dan histori migrasi
  terlebih dahulu. Instalasi lokal yang diperiksa memiliki keempat tabel dan
  halaman laporan dapat dirender, tetapi histori migrasinya belum tercatat.
- Untuk pengujian lokal, pertahankan `CEISA_ENV=sandbox`. Mengaktifkan modul
  bukan berarti koneksi H2H sudah siap; kredensial dan endpoint harus divalidasi
  terpisah sebelum submit dokumen.
- Setelah mengubah flag, jalankan `php artisan config:clear`,
  `php artisan route:clear`, dan `php artisan view:clear`, lalu muat ulang halaman.
- Verifikasi dengan `php artisan route:list --name=customs`.
- Default `config/customs.php` dan `.env.example` tetap nonaktif untuk keamanan
  instalasi baru. `.env` bersifat lokal dan tidak ikut distribusi Git.

PHPUnit: `tests/Feature/PurchaseFinanceNavigationTest.php` menggunakan SQLite
in-memory untuk render DOM, urutan section, target collapse unik, pemetaan
route, active state, label ID/EN/zh_CN, feature flag dan visibilitas ADMIN.
UAT browser tetap diperlukan untuk layout desktop/mobile, scroll, collapse,
tema dan pembungkusan label setelah Google Translate.
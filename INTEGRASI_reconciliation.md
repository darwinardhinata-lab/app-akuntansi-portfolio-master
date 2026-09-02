# Cara Integrasi Tools Rekonsiliasi Jubelio vs ERP

## 1. Salin file ke lokasi berikut

| File yang saya buat | Simpan ke |
|---|---|
| `ReconciliationService.php` | `app/Services/ReconciliationService.php` |
| `ReconciliationController.php` | `app/Http/Controllers/ReconciliationController.php` |
| `reconciliation_index.blade.php` | `resources/views/reconciliation/index.blade.php` |

## 2. Tambahkan route

Tambahkan ini di `routes/web.php` (di dalam grup middleware `auth` yang sudah ada, sejajar dengan route `profit-loss` / `balance-sheet` Anda):

```php
use App\Http\Controllers\ReconciliationController;

Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
Route::post('/reconciliation/compare', [ReconciliationController::class, 'compare'])->name('reconciliation.compare');

// Opsional — export CSV selisih dari server (butuh upload ulang file).
// Tidak wajib dipasang karena view sudah punya tombol export sisi client (JS) tanpa upload ulang.
Route::post('/reconciliation/export', [ReconciliationController::class, 'exportMismatch'])->name('reconciliation.export');
```

## 3. Tambahkan menu (opsional)

Di sidebar/menu Blade Anda, tambahkan link ke `route('reconciliation.index')`, misalnya berdekatan dengan menu Laporan Laba Rugi.

## 4. Format file yang diupload

Tools ini menerima CSV export dari Jubelio dengan kolom minimal: **kode akun, (nama akun), nominal**.
Parser di `ReconciliationService::parseJubelioCsv()` cukup fleksibel — mendukung:

- `77920,Penyesuaian Persediaan Barang Toko Pakel +,572333.20`
- `77920 - Penyesuaian Persediaan Barang Toko Pakel +;Rp 572.333,20;0,1%`
- Delimiter koma maupun titik-koma (auto-detect)
- Format nominal Indonesia (`572.333,20`) maupun umum (`572333.20`)

Jika format export asli Jubelio Anda ternyata cukup berbeda (misal kolom kode akun dan nama akun
digabung dengan pola lain, atau nominal diletakkan di kolom pertama), kirimkan saya 3-5 baris contoh
mentah dari file CSV aslinya — saya sesuaikan fungsi `extractAccountCodeAndName()` /
`extractAmount()` supaya deteksinya presisi 100%, bukan heuristik.

## 5. Cara pakai

1. Export laporan per-akun dari Jubelio untuk periode yang ingin dicek (CSV).
2. Buka `/reconciliation`, upload file tsb, isi tanggal awal/akhir (samakan dengan periode P&L
   yang ingin divalidasi), atur toleransi selisih (default Rp 1.000 — naikkan jika volume transaksi
   besar dan Anda hanya ingin fokus ke selisih material).
3. Klik **Bandingkan**. Tabel hasil akan menandai:
   - 🟢 **Cocok** — selisih ≤ toleransi
   - 🔴 **Nominal Beda** — akun ada di kedua sisi tapi nominal beda di atas toleransi (kasus tipikal:
     data stale/belum di-refresh)
   - 🟡 **Belum di ERP** — ada di sumber Jubelio, tapi belum ter-import ke ledger ERP (baris yang
     hilang saat import, seperti kasus "Toko Pakel" kemarin)
   - ⚪ **Tidak di Sumber** — ada di ledger ERP tapi tidak ada di file Jubelio yang diupload
     (bisa berarti double-posting, atau memang file sumbernya tidak lengkap)
4. Gunakan tombol **Unduh CSV** untuk mengambil daftar akun bermasalah saja, lalu telusuri satu per
   satu ke Jubelio (seperti proses tracing yang kita lakukan untuk akun 77920 Toko Pakel).

## 6. Catatan desain

- Logika exclude opening balance (`evidence_number LIKE 'SA-%'`, `is_opening_balance`, deskripsi
  `SETUP SALDO AWAL`) dan arah saldo per akun (`AccountClassifier` + `normal_balance`) **mengikuti
  logika `ProfitLossController` yang sudah Anda perbaiki** — supaya angka "ERP" di tools ini
  konsisten dengan angka yang muncul di laporan Laba Rugi resmi.
- Tools ini **stateless** (tidak menyimpan hasil ke database) — dijalankan per-request, cocok
  dipakai sebagai langkah verifikasi sebelum tutup buku bulanan, bukan riwayat historis. Kalau nanti
  Anda mau riwayat rekonsiliasi tersimpan (misal untuk audit trail), tinggal bilang — saya tambahkan
  tabel `reconciliation_runs` untuk itu.

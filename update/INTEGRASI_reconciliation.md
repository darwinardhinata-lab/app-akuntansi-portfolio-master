# Cara Integrasi — Update: Dukungan Format Native Jubelio + Indikator Sync

## 1. File yang perlu di-update/ditambahkan

| File | Lokasi | Status |
|---|---|---|
| `ReconciliationService.php` | `app/Services/ReconciliationService.php` | **Replace** versi lama |
| `ReconciliationController.php` | `app/Http/Controllers/ReconciliationController.php` | **Replace** versi lama |
| `reconciliation_index.blade.php` | `resources/views/reconciliation/index.blade.php` | **Replace** versi lama |
| `PATCH_last_sync_indicator.md` | — | Ikuti instruksinya, edit 2 file existing (`ProfitLossController.php` & `report/profit-loss.blade.php`) |

Route & menu **tidak berubah** dari panduan sebelumnya — kalau sudah dipasang, tidak perlu diulang.

## 2. Apa yang baru di ReconciliationService

Sekarang mendukung **2 format sumber**, otomatis terdeteksi:

### Format `coded` (yang lama)
CSV dengan kode akun eksplisit — cocok kalau Jubelio Anda punya menu export
yang menyertakan kode akun.

### Format `native` (BARU — sesuai file yang Anda kirim kemarin)
Persis seperti cetakan/print asli Jubelio yang tidak punya kode akun sama
sekali, hanya:
```
Pendapatan
Penjualan 2.691.835.767,99
Diskon Penjualan -382.951.316,37
...
Total Pendapatan 2.241.838.252,73
Harga Pokok Penjualan
Harga Pokok Penjualan (COGS) 1.325.024.541,82
...
```
Parser akan:
- Mengenali baris section (`Pendapatan`, `Harga Pokok Penjualan`, `Biaya`, `Pendapatan Lainnya`, `Biaya Lainnya`)
- Melewati baris subtotal (`Total ...`, `Laba/Rugi Kotor`, `Laba Usaha`, `Laba/Rugi Bersih`) — tidak dianggap sebagai akun
- Mencocokkan tiap baris ke akun ERP **berdasarkan NAMA akun** (karena tidak ada kode di file ini), dengan 2 tingkat:
  1. **Cocok persis** (nama sudah dinormalisasi — huruf kecil, spasi dirapikan)
  2. **Cocok mirip** (fallback pakai persentase kemiripan teks, ambang 88%) — ditandai badge kuning **"Mirip — cek manual"** di hasil, supaya Anda tetap yang memutuskan valid/tidaknya, bukan sistem menebak diam-diam

### Kontrol kualitas tambahan (khusus format native)
Sistem menghitung ulang jumlah baris yang berhasil dibaca per section, lalu
membandingkan dengan baris `Total Pendapatan` / `Total Biaya` dst yang
tercetak di file. Kalau beda, muncul alert kuning di atas tabel hasil —
tandanya ada baris yang gagal ke-parse (misal karena format nominal yang
tidak biasa), jadi Anda tahu sebelum menyimpulkan "cocok/tidak cocok" dari
data yang sebenarnya tidak lengkap.

## 3. Cara pakai (update)

1. Buka `/reconciliation`.
2. Upload file — **bisa langsung file cetak/export Jubelio yang biasa Anda pakai sekarang**, tidak perlu diolah dulu ke Excel. Kalau Jubelio expor dalam bentuk PDF, convert dulu ke `.txt`/`.csv` (copy-paste isinya ke Notepad lalu simpan `.txt` sudah cukup — parser tidak butuh struktur kolom kaku untuk format native).
3. Biarkan **Format File** di "Otomatis" kecuali Anda ingin memaksa satu format tertentu.
4. Isi tanggal & toleransi seperti biasa, klik **Cek**.
5. Perhatikan:
   - Badge **"X akun dicocokkan via kemiripan nama"** di atas tabel → akun-akun ini WAJIB dicek manual satu-satu, karena namanya tidak identik antara Jubelio dan Master COA ERP (bisa jadi memang akun berbeda, bukan typo).
   - Alert kuning **Kontrol Total** → ada baris yang gagal terbaca, jangan langsung percaya hasil rekonsiliasi sebelum ini dicek.

## 4. Kenapa dua pendekatan (coded vs native) tetap dipertahankan

Karena akurasi pencocokan **by kode akun selalu lebih tinggi** daripada by
nama (nama bisa typo/berubah, kode tidak). Kalau suatu saat Anda menemukan
cara mengeluarkan export Jubelio yang menyertakan kode akun, pakai itu —
sisakan format native sebagai fallback untuk print/export yang memang
tidak punya kode.

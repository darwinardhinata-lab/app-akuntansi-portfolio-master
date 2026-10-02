# Aset kategori MGI — mapping eksplisit dan depresiasi

Finance memilih category berupa COA perolehan 121001–121011 dan akun beban.
Pasangan akumulasi di config/asset_coa.php berasal workbook: building 121002 →
122001, plant 121003 → 122002, sampai production equipment 121010 → 122009.
Beban dipilih eksplisit dari 510006 (produksi), 610002 (penjualan), atau kategori
630001–630009. Server memvalidasi saldo normal dan posisi laporan; akun perolehan
harus cocok jurnal linked. Pemilihan beban tetap tanggung jawab Finance.

Tanah 121001/CIP 121011 wajib umur 0 tanpa beban, tidak menghasilkan depresiasi.
Software, ROU, amortisasi dan kategori di luar registry belum didukung. Aset aktif
belum diklasifikasi menahan batch. Aset nonaktif/future tidak diposting.

Migration 2026_10_02_050000_add_asset_depreciation_expense.php menambah kolom nullable,
tanpa backfill. Belum dijalankan pada database operasional; wajib review/migration
sebelum rollout form/layanan. Data lama category teks tidak dikonversi otomatis.

Service menggunakan straight-line mulai bulan perolehan, dua desimal BCMath dengan
sisa pembulatan pada bulan terakhir. Tidak otomatis catch-up periode terlewat atau
menghitung saldo awal historis. Finance wajib mengesahkan commencement dan saldo
awal sebelum rollout; purchase_date saat ini harus berarti tanggal siap digunakan.
ID JRN-DEP-YYYYMM dan source DEP-YYYYMM mencegah batch periode duplikat.
Jurnal per aset memakai pasangan debit/kredit dan validator balance existing.
Konfigurasi yang sudah memiliki detail jurnal depresiasi baru ditolak dari edit
biasa; histori jurnal lama tanpa penanda aset belum dapat diproteksi oleh linkage
ini dan perlu audit terpisah. Concurrency MySQL belum diuji.

Import diperbaiki: nilai akumulasi tidak lagi dianggap umur manfaat. Baris dengan
akumulasi nonzero ditolak untuk jalur saldo awal khusus; existing asset tidak
ditimpa. Aset baru memerlukan klasifikasi explicit, life 0. assets:sync dibatasi
akun perolehan registry, bukan seluruh prefix 12%; command tidak dijalankan.

Display schedule legacy masih estimasi umur elapsed, bukan rekonsiliasi akumulasi
jurnal. Sinkronisasi jurnal manual/import yang menggunakan single config aset
belum sepenuhnya dialihkan; gunakan audit assets:sync sebelum perolehan historis.
Delete/reversal aset, permission per role, journal opening balance, acquisition
snapshot dan period lock belum lengkap. Ini bukan implementasi fixed asset penuh.

UAT pilihan kategori/biaya, jadwal, audit historis dan review Finance wajib sebelum
rollout. Tidak ada perubahan data produksi, migration operasional atau commit/push.
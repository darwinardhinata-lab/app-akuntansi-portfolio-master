# Pengaturan Bea Cukai

Administrator membuka Administrasi → Pengaturan Bea Cukai (`/settings/customs`).
Mode yang tersedia saat ini adalah data internal tanpa H2H. Pilihan H2H terlihat
tetapi terkunci di UI dan ditolak server, sampai kontrak API CEISA dan ownership
perusahaan diverifikasi. Pengaturan ini tidak mengaktifkan `CEISA_ENABLED`.

`cbr_settings` menyimpan pilihan sinkronisasi otomatis (default aktif).
Ketujuh laporan membangun snapshot dari sumber operasional:

1. Pemasukan: penerimaan pembelian dan material berstatus POSTED.
2. Pengeluaran: invoice barang yang memiliki stock-out ledger terkait.
3. Mutasi Bahan Baku: material ledger, termasuk saldo sebelum periode.
4. WIP: hasil cutting OK dikurangi reject finishing dan penerimaan barang jadi
   per SPK sampai akhir periode; bukan jumlah rencana SPK.
5. Mutasi Barang Jadi: inventory ledger untuk produk Work Order.
6. Barang Modal: kuantitas perolehan dari register aset. Histori pelepasan belum
   tersedia; status nonaktif tidak ditebak sebagai pengeluaran.
7. Reject/Sisa: kejadian penerimaan reject, cutting reject/scrap/wastage,
   dan finishing reject; setiap kejadian dan satuan dipisahkan. Histori
   pengeluaran reject belum tersedia, saldo adalah akumulasi tercatat.

Sinkronisasi berjalan saat draft dibuat, dibuka, diekspor dan difinalisasi,
serta melalui tombol sinkronisasi ulang. Seluruh baris draft dibangun ulang
secara transaksional, termasuk menghapus transaksi sumber yang sudah batal
atau dihapus. Baris manual/import akan tergantikan; tombol import disembunyikan
pada mode otomatis. FINAL/DIUNGGAH tidak diubah. Tidak ada background sync
atau request CEISA. Data pabean yang tidak tersedia dibiarkan kosong, termasuk
tanggal dokumen (nullable); laporan internal belum merupakan bukti persetujuan DJBC.

Deploy hanya migrasi baru `2026_10_06_100001_create_cbr_settings_table.php`.
Jalankan juga `2026_10_06_110001_allow_missing_internal_customs_date.php`.
Jangan menjalankan migrate:fresh pada database operasional. Kredensial CEISA
tidak disimpan pada tabel pengaturan atau audit log ini.
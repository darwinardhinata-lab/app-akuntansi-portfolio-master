<?php
/**
 * Find hardcoded Indonesian text in HTML attributes (placeholder, title).
 * Output: file|line|attribute|value
 */

$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\resources\\views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$indonesian_words = ['Masukkan', 'Pilih', 'Cari', 'Ketik', 'Nomor', 'Jumlah', 'Tanggal', 'Nama', 'Kode', 'Deskripsi', 'Alamat', 'Telepon', 'Email', 'Password', 'Kembali', 'Batal', 'Simpan', 'Tambah', 'Edit', 'Hapus', 'Detail', 'Laporan', 'Dari', 'Sampai', 'Hingga', 'Atau', 'Dan', 'Tidak', 'Belum', 'Sudah', 'Aktif', 'Contoh', 'Minimal', 'Maksimal', 'Total', 'Nilai', 'Status', 'Tipe', 'Jenis', 'Kategori', 'Grup', 'Divisi', 'Perusahaan', 'Pengguna', 'Pelanggan', 'Pemasok', 'Supplier', 'Produk', 'Barang', 'Akun', 'Rekening', 'Bank', 'Gudang', 'Faktur', 'Pemesanan', 'Pembelian', 'Penjualan', 'Pembayaran', 'Penerimaan', 'Pengeluaran', 'Transaksi', 'Jurnal', 'Buku', 'Besar', 'Neraca', 'Laba', 'Rugi', 'Arus', 'Kas', 'Modal', 'Hutang', 'Piutang', 'Stok', 'Persediaan', 'Produksi', 'Biaya', 'Pendapatan', 'Beban', 'Saldo', 'Awal', 'Akhir', 'Periode', 'Bulan', 'Tahun', 'Hari', 'Minggu', 'Jam', 'Menit', 'Detik', 'Silakan', 'Opsional', 'Wajib', 'Kolom', 'Baris', 'Halaman', 'Menu', 'Tombol', 'Gambar', 'Foto', 'Video', 'File', 'Data', 'Sistem', 'Pengaturan', 'Profil', 'Keluar', 'Masuk', 'Daftar', 'Log', 'Catatan', 'Keterangan', 'Komentar', 'Pesan', 'Notifikasi', 'Peringatan', 'Kesalahan', 'Sukses', 'Berhasil', 'Gagal', 'Proses', 'Selesai', 'Batal', 'Tutup', 'Buka', 'Simpan', 'Kirim', 'Ambil', 'Buat', 'Ubah', 'Hapus', 'Tambah', 'Lihat', 'Cari', 'Filter', 'Urutkan', 'Ekspor', 'Impor', 'Cetak', 'Unduh', 'Unggah'];

$matches = [];
foreach ($views as $v) {
    $content = file_get_contents($v);
    $lines = explode("\n", $content);
    foreach ($lines as $line_num => $line) {
        if (preg_match_all('/\s(placeholder|title)=["\']([^"\']+)["\']/', $line, $attr_m, PREG_SET_ORDER)) {
            foreach ($attr_m as $am) {
                $attr = $am[1];
                $val = $am[2];
                if (str_contains($val, '__(') || str_contains($val, '{{') || str_contains($val, '$') || str_contains($val, '<?php')) continue;
                foreach ($indonesian_words as $word) {
                    if (stripos($val, $word) !== false) {
                        $rel_path = str_replace('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\', '', $v);
                        $matches[] = "$rel_path|$line_num|$attr|$val";
                        break;
                    }
                }
            }
        }
    }
}

echo implode("\n", $matches) . "\n";
echo "\n--- TOTAL: " . count($matches) . " ---\n";

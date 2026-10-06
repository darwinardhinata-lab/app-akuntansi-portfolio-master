# Struktur laporan IT Inventory Bea Cukai

Layout mengikuti lima screenshot referensi yang diberikan pada 6 Oktober 2026.
`ReportLayout` menjadi kontrak kolom bersama tabel dan export XLSX.

- Pemasukan (17 kolom): No; Dokumen Pabean (Jenis, No Aju, Nomor,
  Tanggal); BPB (Nomor, Tanggal); Pemasok/Pengirim; Kode barang; Nama
  barang; QTY; Unit; Bruto; Netto; Nilai Barang; Currency; Harga IDR.
- Pengeluaran (15 kolom): No; Dokumen Pabean (Jenis, Nomor, Tanggal);
  BPB (Nomor, Tanggal); Penerima; Kode barang; Nama barang; QTY; Unit;
  Bruto; Netto; Nilai Barang; Currency.
- Mutasi (12 kolom): No; Kode Barang; Nama Barang; Satuan; Saldo Awal;
  Pemasukan; Pengeluaran; Penyesuaian; Saldo Akhir; Stock Opname;
  Selisih; Keterangan.
- WIP (6 kolom): No; Kode Barang; Nama Barang; Satuan; Jumlah; Keterangan.

Barang modal dan reject memakai format mutasi; screenshot khusus kedua
laporan tersebut belum diberikan. Tidak ada perubahan sumber transaksi,
aturan finalisasi, maupun periode bulanan. Filter tanggal bebas, pencarian,
pagination seperti screenshot, dan download PDF belum ditambahkan.

Nomor aju, bruto, netto, dan harga IDR disimpan pada snapshot dokumen
melalui migrasi tambahan tanpa menghapus kolom historis faktur pajak.
Kolom faktur pajak tidak ditampilkan dalam layout baru.
Sinkronisasi internal memakai nilai transaksi IDR sebagai Harga IDR.
Nomor aju dan bobot tetap kosong jika sumber internal belum mencatatnya;
nilai valuta asing tidak dikonversi menggunakan kurs perkiraan.
Status pencacahan lama `Belum`/`Sudah` tidak dianggap kuantitas Stock Opname.

XLSX menggunakan header datar sesuai urutan tabel; identifier disimpan
sebagai teks untuk mempertahankan nol awal dan nomor aju panjang.
Angka disimpan sebagai numeric cell. Template import memakai header unik
untuk membedakan nomor/tanggal dokumen pabean dari BPB. Import mendukung
alias baru dan header lama untuk kompatibilitas data historis.
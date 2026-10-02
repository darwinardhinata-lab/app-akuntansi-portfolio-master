# Pembersihan sumber dana Payment Plan MGI

Sumber: d:\Project ERP\coa_export_20261002020629.xlsx (212 baris termasuk header).
Dump database baru belum diterima/dieksekusi; workbook bukan verifikasi runtime DB.

## Perubahan

Opsi sumber dana memakai kode COA dan nama master accounts, bukan nama perusahaan
lama. Akun IDR terverifikasi: 111001/111002 kas, 111101/111102/111103 Mandiri IDR.
Eligibility memerlukan tipe Cash & Bank, normal DEBET, laporan NERACA. Rekening
USD 111201/111202 tidak diaktifkan sebelum currency/rate tersedia; 1121xx deposito
tidak dianggap rekening pembayaran biasa. Tidak ada fallback bank/kas pertama.
Field jenis_transaksi menyimpan kode sumber COA pada pilihan baru; jurnal kredit
memakai persis kode terpilih dan memvalidasinya ulang setelah lock.

Label rekening perusahaan lama di UI/terjemahan Payment Plan dan contoh template
import dihapus. Template memakai PENDING/PENGAJUAN, tanpa COA/rekening fiktif.
Data historis tidak ditransformasikan atau dihapus. Nilai lama/ambigu pada PAID
ditolak saat posting; perlu audit koreksi tersendiri, jangan dipetakan diam-diam.

## Batas

Belum mengubah config/coa.php global, mapping modul lain, seeder legacy, database
fisik, backup/dump, histori dokumen/jurnal. Arti akun tidak cukup untuk menetapkan
semua kebijakan posting (pajak, aset, uang muka, manufaktur) secara otomatis.
Tidak mengklaim seluruh residu legacy aplikasi selesai. Identitas perusahaan,
runtime cache, profil, serta master/transaction pada database baru perlu audit
read-only lanjutan saat dump/sumber database terbaru tersedia.

Sebelum rollout pastikan master aktif sesuai export, UAT pilihan sumber dana dan
rekonsiliasi kredit jurnal, serta kesiapan PP historis. USD/deposito sengaja ditolak.
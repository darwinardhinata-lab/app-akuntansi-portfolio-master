# Perbaikan COA MGI — status paket fondasi

Paket ini mengganti default inti legacy dengan akun workbook MGI untuk AR/AP,
VAT, supplier/customer advance, FG, HPP, discount, return, shipping income dan
rounding. Tidak mengubah environment override atau transaksi historis.

Key ambigu kini kosong: sales type, exceptional discount/other income, aset dan
depresiasi global, fabric stage, subcontract liability, jasa/wastage/variance.
Posting Eloquent JournalDetail create/insert menolak kode kosong; bukan validator
seluruh semantic akun atau seluruh raw SQL. Import fabric menolak sebelum menulis
jika mapping tidak tersedia. Transaksi terkait perlu konfigurasi disahkan sebelum
rollout; jangan mengisi akun perkiraan hanya agar transaksi lolos.

CompanyCoaResolver diperketat ke kandidat registry MGI dan posisi laporan sesuai
semantic key. Ini khusus registry MGI: customization account memerlukan perubahan
registry disahkan, bukan sekadar mengganti row mapping. Fixture beban di test
disesuaikan ke LABA RUGI, bukan melonggarkan validator.

Laporan uang muka penjualan memakai 231001, bukan AP. Label template saldo awal
piutang diperbaiki. Depresiasi memeriksa source_doc_no untuk mencegah duplikasi
setelah nomor bukti internal berubah, rollback pada duplikat, mengecualikan aset
nonaktif dan menolak mapping global kosong.

## Belum selesai / keputusan diperlukan

- Resolver global per-company serta validasi semua override akun existing/semantic.
- Sales lokal/ekspor/jasa per dokumen, pemisahan other_discount dan returnRemaining.
- Matrix aset berdasarkan category/akun perolehan, land/CIP tidak disusutkan,
  biaya depresiasi per fungsi, audit assets:sync prefix 12%.
- Knitting/processing stage mapping, master fabric classification, raw SQL import.
- Seeder/sync legacy tidak dihapus; jangan dijalankan pada MGI tanpa audit/guard.
- Readiness database operasional belum diperiksa; dump baru belum diterima.

Paket fondasi bukan klaim seluruh tahap selesai. Tidak ada migration, perubahan
data produksi, config cache deployment, commit/push. Full test memakai SQLite
in-memory; UAT dan audit readiness wajib sebelum penggunaan operasional.
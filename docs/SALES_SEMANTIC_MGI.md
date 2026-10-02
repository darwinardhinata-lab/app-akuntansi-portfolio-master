# Pilihan jenis penjualan MGI — paket barang

Invoice langsung dan modal pengiriman SO mewajibkan pilihan LOCAL atau EXPORT.
Mapping tetap ke COA workbook 411001/411002, divalidasi ada, tipe Sales, normal
KREDIT dan posisi LABA RUGI. Pilihan dan snapshot kode revenue disimpan pada invoice.
Tidak ada default lokal. SERVICE/411003 tidak diaktifkan pada alur stok barang;
invoice jasa murni masih memerlukan flow terpisah tanpa stock/HPP.

Migration `2026_10_02_040000_add_sales_semantic_to_invoices.php` menambah dua kolom
nullable, tanpa backfill historis. Migration hanya diuji SQLite in-memory; tidak
dijalankan pada DB operasional. Operator wajib meninjau backup/migration sebelum
rollout. Jangan deploy kode penerbitan invoice tanpa kolom tersebut.

SalesOrderService memeriksa ownership dulu kemudian semantic. Caller import/
auto-ship yang belum menyertakan sales_semantic ditolak; tidak menggunakan global
COA_PENJUALAN sebagai fallback. FastImportINV raw path belum dialihkan ke semantic
ini dan tetap memerlukan audit/readiness tersendiri; jangan jalankan pada produksi.

Invoice langsung kini memeriksa balance sebelum insert detail dan menyimpan
journal_id. Mapping diskon/biaya khusus yang kosong tetap fail-closed dari paket
fondasi. Pilihan EXPORT bukan implementasi currency/FX/CEISA atau validasi pajak
ekspor; jumlah masih memakai mekanisme existing. Transaksi valas belum didukung
oleh perubahan ini.

Regression test membuktikan invoice LOCAL/EXPORT ke revenue tepat, linkage jurnal,
penolakan semantic kosong/SERVICE dan akun normal salah. Ownership test existing
tetap harus lulus. UAT browser serta verifikasi database diperlukan sebelum rollout.

Belum selesai: pilihan kategori aset/perolehan/akumulasi/beban, stage fabric dan
maklun, import semantic, selection exception discount/variance, serta guard raw SQL.
Paket ini tidak menyatakan seluruh rencana lintas modul selesai.
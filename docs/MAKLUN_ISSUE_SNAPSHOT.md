# Fondasi snapshot issue maklun

Migration 2026_10_02_060000 menambah source_account_code nullable pada yarn/fabric
issues, tanpa backfill. Belum dijalankan di database operasional.

Issue baru default nonaktif melalui platform.maklun_issue_enabled / environment
PLATFORM_MAKLUN_ISSUE_ENABLED (false). Jangan aktifkan sebelum lifecycle receipt
dan reversal lengkap. Receipt masih diblokir eksplisit, sehingga flag issue bukan
tanda siap produksi. Tidak ada environment deployment diubah.

Ketika issue diizinkan pada lingkungan test/readiness, akun asal master divalidasi
dan disnapshot sebelum stock movement. Yarn harus 114003; fabric memilih akun
registry persediaan eksplisit dan processing memerlukan GREY. Status harus OPEN/
ISSUED; qty positif finite. Issue bersnapshot tidak bisa dihapus via void legacy.
Histori legacy tidak diubah dan void legacy lama belum diganti append-only.

Belum selesai: snapshot receipt/destination/liability, consumption/partial/return
allocation, reversal stock value/journal append-only, serta audit issue historis.
Service issue masih memakai mekanisme fisik existing tanpa journal reclassification;
pilihan inventory-at-subcontractor/GRNI membutuhkan kebijakan Finance. Jangan
backfill akun issue lama dari master sekarang.

Test memeriksa default closed sebelum perubahan, kolom/model metadata dan guard
status/qty/account. Belum membuktikan end-to-end issue/receipt/reversal atau
concurrency MySQL. UAT dan review Finance wajib sebelum rollout.
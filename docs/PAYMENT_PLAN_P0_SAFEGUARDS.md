# Payment Plan — P0 safeguards

## Perilaku yang diubah

- Update/delete biasa ditolak untuk PAID/POSTED atau PP yang mempunyai linkage
  journal_id, ID jurnal deterministik, source_doc_no bertipe Payment Plan, atau
  bukti legacy JRN-{no_transaksi}. Jurnal existing tidak dihapus oleh destroy PP.
- COA, rekening sumber, dan perubahan status juga dikunci untuk kondisi tersebut.
- POSTED hanya dihasilkan endpoint posting, bukan dropdown status atau import CSV.
  Baris CSV POSTED ditolak dan dilaporkan; tidak dikonversi diam-diam.
- Update/delete PP ditolak jika PO sintetis atau ref_po_number sudah menerima
  barang (qty_received positif, status receiving, atau mode GRN_V1).
- Pemeriksaan dilakukan dalam transaksi setelah lock PP; pemeriksaan PO mengambil
  lock PO. SQLite regression test tidak membuktikan concurrency MySQL produksi.
- Form edit PAID/POSTED tampil read-only. Kontrol status, rekening, COA, dan delete
  di daftar mengikuti status tersebut; server tetap menjadi pengaman utama untuk
  status historis yang tidak konsisten dengan jurnal.

## Batas lingkup dan dampak operasional

Penguncian konservatif mencakup lampiran dan seluruh edit form biasa setelah
pembayaran. Jika bukti atau COA PAID perlu dikoreksi, hubungi Finance/Accounting;
jalur koreksi khusus berotorisasi belum diimplementasikan. Jangan membuka status
atau menghapus jurnal manual hanya untuk melewati proteksi.

Posting existing tetap berjalan: tidak menambahkan approval berjenjang, permission
per aksi, validasi proof of payment, batas nominal, bank maker/checker, allocation
Bill, atau reversal. Update status menuju PAID masih mengikuti mekanisme existing;
P0 ini bukan implementasi SOP penuh. Import PAID historis masih diizinkan dan
memerlukan audit dokumen terpisah. Metadata approval/re-approval belum tersedia.

Tidak ada migration/seeding, perubahan database operasional, feature flag, COA,
numbering, commit/push, atau pencabutan cookie. Koreksi data historis tidak otomatis.

## Validasi

PaymentPlanSafeguardsTest memeriksa blocked update/delete/status/COA/rekening,
preservasi detail dan file, nominal aktual berubah tanpa nominal pengajuan berubah,
link jurnal dengan status tidak konsisten, PO receiving sintetis/real, import POSTED,
edit/delete belum dibayar, serta render read-only dalam ID/EN/zh_CN.

UAT browser dan test concurrency MySQL terisolasi perlu dilakukan sebelum rollout.
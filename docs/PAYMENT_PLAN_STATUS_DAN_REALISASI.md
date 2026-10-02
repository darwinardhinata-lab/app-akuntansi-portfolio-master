# Payment Plan: transisi status dan konfirmasi realisasi

## Lingkup tahap ini

Tidak mengubah schema atau menjalankan migration. Tidak menetapkan limit atau
role baru. Ini bukan approval berjenjang, bank maker/checker, atau integrasi bank.
Role legacy ADMIN/FINANCE/STAFF belum memetakan mandat operasional SOP; endpoint
masih mengikuti autentikasi existing. Pemisahan orang/permission belum ditegakkan.

## Flow server

- PENGAJUAN → APPROVED atau REJECTED.
- APPROVED → PENGAJUAN (revisi), REJECTED, atau PAID (konfirmasi manual).
- REJECTED tidak dapat diaktifkan kembali lewat status; ajukan dokumen baru.
- PAID/POSTED tetap terkunci untuk perubahan biasa. POSTED hanya lewat posting.
- Edit approved atau penetapan COA/sumber dana mengembalikan ke PENGAJUAN,
  termasuk perubahan bernilai sama (konservatif, belum tersedia approval snapshot).

## Konfirmasi PAID

Sebelum konfirmasi: edit/detail harus memuat nominal aktual eksplisit positif
maksimal dua desimal dan file bukti lokal existing pada setiap detail. Header
aktual harus sama dengan total detail dan tidak melebihi nominal pengajuan.
Tanggal realisasi, COA existing, dan jenis sumber dana selain PENDING wajib ada.
Nominal nol/negatif, URL eksternal, file hilang, atau aktual kosong ditolak.

Operator harus melengkapi data sebelum approval akhir; perubahan setelah approval
membutuhkan persetujuan ulang. Bukti existing bisa merupakan bukti pendukung,
bukan otomatis bukti settlement bank. Pemeriksaan isi bukti, tanggal/referensi
bank, serta keabsahan realisasi tetap tanggung jawab Finance/Treasury.
SystemLog transisi mencatat pengguna existing, timestamp, nomor PP, status lama/
baru, nominal aktual dan tanggal realisasi. Belum menyimpan approval berjenjang
atau bank checker/reference terstruktur.

## Posting

Hanya PAID dengan realisasi lengkap. Validasi ulang dilakukan setelah lock row PP
dalam transaksi; ID jurnal deterministik diperiksa kembali. Akun debit dan kredit
harus existing. Status POSTED dan linkage journal_id disimpan bersama jurnal.
Posting memakai nominal aktual; fallback pengajuan tidak dapat melewati validasi.
SQL SUBSTR dipakai untuk filter prefix akun agar kompatibel MySQL/SQLite.

## Batas / risiko rollout

- Seleksi akun bank/kas tetap legacy, bukan pemetaan eksplisit rekening perusahaan.
- Bill PAID/allocation/outstanding masih legacy; partial payment belum diselesaikan.
- Import APPROVED/PAID historis masih diizinkan tanpa membuktikan approval/bank.
  Import POSTED tetap ditolak; posting PAID import harus lolos realisasi.
- PAID historis tidak lengkap ditolak saat posting dan tidak dapat diedit biasa.
  Audit read-only dan jalur koreksi berotorisasi diperlukan sebelum rollout;
  jangan downgrade status/hapus jurnal untuk melewati proteksi.
- Berkas di public disk bukan penyimpanan private/immutable; hardening lampiran
  perlu tahap tersendiri. Keberadaan file bukan pemeriksaan isi atau checksum.
- Belum ada jalur khusus koreksi COA/lampiran PAID, reversal, atau period lock.
- Uji SQLite membuktikan perilaku fungsional, bukan concurrency lock MySQL.

## Validasi

Regression test mencakup urutan status, file hilang, nominal aktual kosong/
over-limit/lebih dari dua desimal, reapproval edit/COA/rekening, posting hanya PAID,
posting ulang tidak menduplikasi, nominal aktual dan journal_id, serta P0 safeguards.
UAT browser, review Finance, dan audit readiness data historis wajib sebelum rollout.
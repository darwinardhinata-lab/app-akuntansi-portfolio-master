# Koreksi terbatas PAID sebelum jurnal

## Otorisasi yang disepakati

Hanya pengguna dengan role legacy FINANCE DAN ID dalam konfigurasi
platform.payment_correction_user_ids. Default kosong; ADMIN tidak mendapat bypass.
Operator boleh menetapkan environment PAYMENT_CORRECTION_USER_IDS sebagai daftar
ID user dipisahkan koma setelah mandat Finance disahkan. Tidak ada ID produksi
yang diisi atau .env yang diubah oleh implementasi ini. Config cache deployment
harus diperbarui melalui prosedur operator; tidak otomatis dijalankan.

## Endpoint dan form

GET /payment-plan/{id}/correction dan POST pada URL yang sama, dengan auth existing.
POST rate-limited 10 per menit. Link muncul di halaman edit hanya bagi operator
allowlisted pada dokumen PAID tanpa jurnal. Server mengecek ulang status/link jurnal
setelah lock row PP dan mengunci detail terkait sebelum menulis.

Yang boleh:
- Mengganti/melengkapi COA dengan akun existing.
- Menambahkan JPG/PNG/PDF maksimal 5 MB pada detail milik PP yang belum memiliki
  berkas lokal tersedia. Form menambahkan satu bukti per penyimpanan; API maksimal
  50 detail distinct dalam satu request.
- Alasan wajib 10–1000 karakter; tidak boleh perubahan kosong.

Yang ditolak:
- PAID yang sudah mempunyai journal_id atau jurnal deterministik/sumber/legacy.
- POSTED dan status selain PAID.
- Pengguna non-FINANCE, FINANCE di luar allowlist, administrator, dan anonim.
- Payload nominal, status, rekening, tanggal, vendor, Bill/PO, dan field lain.
- Detail PP lain atau penimpaan bukti lokal existing.

## Histori dan konsistensi

Status tetap PAID; nominal dan data realisasi tidak berubah. Bukti lama tidak
dihapus, meskipun path lama tidak tersedia/merupakan referensi eksternal. Path lama
dan baru dicatat di SystemLog bersama alasan, COA lama/baru, dan SHA-256 file baru.
SystemLog existing menyimpan user, timestamp, IP. Log ditulis dalam transaksi yang
sama; bila gagal, perubahan DB rollback dan file baru dibersihkan. Ini bukan audit
log immutable dan tidak menggantikan pengamanan akses database/backup.

## Batas operasional

COA yang dipilih harus ditinjau Finance; belum ada mapping eligibility per kategori.
Untuk pembayaran hutang, posting existing tetap memakai akun AP konfigurasi, bukan
COA arbitrer. Koreksi ini tidak memperbaiki allocation Bill/bank mapping legacy.
PP historis dengan nominal aktual kosong/lebih besar dari pengajuan tetap tidak
dapat diposting; nominal tidak boleh diperbaiki lewat endpoint ini. Jalur koreksi
nominal berotorisasi masih perlu keputusan terpisah.

Bukti public disk mengikuti pola existing, belum private/immutable. Keberadaan
file/MIME/hash bukan konfirmasi bank atau pemeriksaan isi bukti. Perlu UAT browser,
review Finance dan uji concurrency MySQL terisolasi sebelum rollout. Tidak ada
migration, perubahan data historis, pengisian allowlist atau commit/push otomatis.
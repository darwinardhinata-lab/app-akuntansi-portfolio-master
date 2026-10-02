# Otorisasi reversal maklun

Kontrol fail-closed: role FINANCE DAN user ID dalam platform.maklun_reversal_user_ids
(environment MAKLUN_REVERSAL_USER_IDS, koma dipisahkan). Default kosong; ADMIN tidak
bypass. Allowlist koreksi Payment Plan terpisah dan tidak memberikan akses maklun.
Operator wajib mendapatkan mandat Finance sebelum mengisi ID; .env tidak diubah.

Empat endpoint reversal knitting/processing serta service reversal sendiri memeriksa
otorisasi. Alasan wajib trimmed 10–1000 karakter sebelum dokumen dibaca; service
mengecek lagi untuk caller non-HTTP. UI menampilkan form hanya bagi user berwenang.
Metadata reason/reversed_by disimpan pada issue/receipt dalam transaksi bersama
stok/jurnal dan SystemLog. Date reversal ditentukan server, tidak menerima tanggal
posting retroaktif atau nominal bebas. Ini otorisasi single operator, bukan maker-
checker dua approver; belum punya approval ticket/period lock.

Migration 2026_10_02_090000 menambah dua kolom nullable pada empat tabel. Tidak
dijalankan pada DB operasional; tidak backfill metadata lama. Flag issue/receipt
tetap nonaktif. UAT browser dan review permission/database readiness diperlukan.

Trigger database dan concurrency MySQL belum diterapkan/diuji; guard seal tetap
app-layer. Test auth memakai middleware aktif; lifecycle test direct service memakai
FINANCE allowlisted eksplisit dan reason. Tidak ada commit/push/deployment otomatis.
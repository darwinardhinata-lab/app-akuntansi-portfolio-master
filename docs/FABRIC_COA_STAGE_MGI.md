# Pemilihan fungsi COA fabric MGI

Master create/update dan import mewajibkan inventory_account_code eksplisit:
114003 bahan baku, 114008 setengah jadi atau 114002 WIP. Akun harus existing,
normal DEBET, posisi NERACA. GREY/FINISHED bukan dasar mapping otomatis.
Nama akun tampil dari master, tidak memakai nomor/nama perusahaan lama.

Import constructor menerima encoding dan kode akun; tidak memakai config fabric
global sebagai fallback. Satu pilihan berlaku untuk seluruh file. Import berada
dalam transaksi dan menolak perubahan akun fabric bersaldo; seluruh file rollback.
Form master menolak perubahan akun bersaldo. Histori issue ketika saldo sudah nol
belum merupakan guard master lengkap dan perlu snapshot sebelum rollout maklun.

## Maklun: belum selesai, diblokir eksplisit

Receipt knitting/processing diblokir sebelum perubahan stok/jurnal karena source
account saat issue belum disnapshot. Mengisi COA_MFG_FABRIC/HUTANG_JASA tidak
membuka blokir ini. Jangan mengeluarkan bahan baru ke jalur tersebut sebelum
paket lifecycle siap; issue existing sendiri belum diubah oleh paket ini.

Tahap berikut wajib: snapshot source account di setiap issue, snapshot destination
dan liability pada receipt, pilihan accrued subcontract vs AP invoice, reklasifikasi
inventory-at-subcon/WIP jika disahkan, serta reversal append-only. Data issue lama
harus direkonsiliasi, bukan backfill dari master terbaru. Perlakuan partial receipt,
returned yarn/fabric, rejected qty dan kapitalisasi cost perlu test lifecycle.

Tidak ada migration/data operasional berubah pada paket ini. Tidak mengklaim
maklun selesai. Full suite SQLite lulus untuk master/import; tidak membuktikan
workflow knitting/processing siap operasional atau concurrency MySQL.
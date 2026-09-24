# Rancangan transisi GRN dan Payment Request — belum diaktifkan

## Bukti dari source terbaru

| Bagian | Kondisi source | Implikasi |
|---|---|---|
| `PurchaseOrderService::receivePartialOrder` | Mengunci PO, mengubah qty/stok/average cost, membuat inventory ledger dan jurnal Purchase Bill dalam transaction | Memanggil alur ini lalu posting GRN secara terpisah berisiko menggandakan efek |
| Purchase Bill linkage | UPDATE `purchase_bills` berdasarkan bill_number | Tidak menjamin bill header ada jika belum diimport/dibuat |
| `PostingService::post` | Memvalidasi balance dan persist header/detail; nominal dibangun caller | Transaction boundary, periode, dan idempotensi bukan otomatis dijamin service ini |
| PO/SO | Tidak ditemukan `company_id` dalam migration terkait | Mapping ownership dibutuhkan sebelum isolasi dokumen |
| GRN | Model/skema tersedia | Belum berarti receiving sudah memakai GRN |
| Payment Request | Enum DRAFT/SUBMITTED/APPROVED/POSTED/REJECTED/VOID pada migration | Jangan memakai enum PAID dari rancangan lama |
| Party | Link nullable ke PO/SO tersedia | Tidak menjadi bukti kepemilikan seluruh transaksi legacy |

## Keputusan yang dibutuhkan sebelum aktivasi

| ID | Keputusan | Usulan teknis yang dapat direview |
|---|---|---|
| D01 | Company pemilik transaksi legacy | Mapping eksplisit dari sumber yang disetujui; ambigu masuk antrean review. Jangan assign semua ke BBW hanya karena company seed pertama |
| D02 | Waktu pengakuan kewajiban/biaya saat receipt atau bill | Pertahankan baseline sampai pemilik akuntansi menyetujui pemisahan; GRNI/akun penampung tidak dibuat otomatis |
| D03 | Approval dan organisasi AS05 | Framework memakai rules berversi; org placeholder tidak digunakan untuk assignment approver produksi |
| D04 | Rekening sumber perusahaan dan mapping akun | Master perusahaan dan currency eksplisit; rekening Party adalah rekening penerima, bukan sumber |
| D05 | Arti POSTED dan settlement | POSTED menunjukkan pencatatan sesuai keputusan bisnis; status bank/settlement dilacak terpisah bila belum ada bukti transfer |
| D06 | Dokumen bill manual tanpa PO | Tetapkan apakah berdampak stok atau hanya keuangan; wajib mencegah stok dua kali ketika kemudian ditautkan receipt |

## Alternatif GRN

**Alternatif A — jejak GRN pada kejadian receiving legacy.** Satu transaction mencatat GRN/detail lalu menjalankan efek stok/jurnal legacy satu kali melalui satu orchestrator. GRN mengacu jurnal yang sama, tidak membuat jurnal kedua. Ini mempertahankan waktu pengakuan baseline, tetapi belum memisahkan receipt dan billing secara bisnis. Perlu solusi eksplisit untuk receipt tanpa bill existing serta kunci idempotensi yang stabil.

**Alternatif B — GRN terpisah dari Bill.** Receipt memengaruhi stok dan pengakuan sesuai kebijakan yang disetujui; Bill menyelesaikan alokasi dan akun penampung jika digunakan. Ini memerlukan keputusan D02, mapping COA, desain selisih harga/pajak/retur, dan migrasi yang lebih luas. Tidak boleh diterapkan hanya dengan menambah controller.

Rekomendasi urutan: selesaikan D01 dan uji A1, review A untuk langkah kompatibilitas awal, lalu pilih B hanya jika kebutuhan bisnis mengharuskan pemisahan receipt-bill. Keduanya bukan otorisasi otomatis untuk mengubah jurnal.

## Kontrak implementasi berikutnya

1. Dokumen mempunyai company ownership yang tervalidasi. Party, dokumen sumber, lokasi, dan rekening harus cocok company.
2. GRN detail mengacu detail PO; alokasi bill mengacu receipt/detail dengan qty/nilai yang dapat direkonsiliasi. Kebutuhan satu-ke-banyak dan banyak-ke-banyak harus ditentukan sebelum migration.
3. Draft tidak mengubah stok/jurnal. Posting atomic mencakup perubahan status, qty, ledger, link journal dan idempotency result.
4. Idempotency key unik menurut company + jenis kejadian + kunci sumber; payload hash berbeda untuk key sama ditolak. Retry mengembalikan hasil sebelumnya.
5. Routing legacy/baru stabil per dokumen. Feature flag global tidak boleh memindahkan dokumen yang sudah sebagian diproses ke jalur lain.
6. Payment Request dan permintaan pembelian merupakan entitas berbeda; jangan memakai PR tanpa kepanjangan.
7. Pelunasan bill tidak mengakui biaya/hutang untuk kedua kali. Pembayaran parsial memakai allocation dan penguncian outstanding.
8. Jurnal posted tidak dihapus; reversal terkait sumber dengan kebijakan periode. Jalur void legacy harus dimigrasikan secara terpisah dan direkonsiliasi.
9. POSTED bukan bukti transfer bank berhasil. Pengiriman eksternal baru dilakukan melalui mekanisme after-commit/outbox dan environment yang diotorisasi.
10. Test utama: PO 100 diterima 40+60; overreceipt; receipt paralel; bill tanpa PO; pembayaran 4 juta+6 juta atas 10 juta; overpayment paralel; retry; failure tengah transaction; periode tertutup; reversal; rekonsiliasi stok/jurnal.

## Titik berhenti saat ini

Tidak ada implementasi GRN/Payment Request aktif dalam paket A1. Mengaktifkannya sebelum ownership transaksi dan aturan posting ditetapkan akan memerlukan tebakan yang memengaruhi saldo nyata. Source A1, test dan dokumen transisi disiapkan agar implementasi berikutnya dapat dilanjutkan dari dasar yang konkret.

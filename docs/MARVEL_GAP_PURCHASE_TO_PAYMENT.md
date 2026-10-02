# Gap aktual Purchase → GRN → Bill → Payment

## Batas audit

Inspeksi source pada baseline a9e354d dan perubahan navigasi lokal. Tidak membaca
data operasional atau nilai .env, tidak menguji settlement pada database produksi.
Temuan adalah perilaku source yang diperiksa, bukan hasil audit transaksi historis.
Path berikut relatif terhadap D:\xampp\htdocs\app-akuntansi-portfolio-master.

## Matriks bukti

| Area | Bukti source | Kondisi / gap |
|---|---|---|
| PO | app/Http/Controllers/PurchaseOrderController.php: store | PO manual dibuat langsung APPROVED. Belum merupakan maker-checker PO. |
| Receiving | app/Services/PurchaseOrderService.php: receivePartialOrder | Dua jalur: legacy dan GRN_V1. Jangan migrasikan PO berjalan secara diam-diam. |
| GRN | app/Services/GrnReceivingService.php: receive | Lock company/PO, UUID request key, payload hash, batas qty, validasi mapping COA, tautan Bill/jurnal. |
| Batas GRN V1 | GrnReceivingService.php | Satu receipt menghasilkan satu Bill dan menggunakan event stok/jurnal legacy yang sama. Uang muka/pajak/diskon ditolak. Bukan receiving dan AP posting terpisah/three-way matching lengkap. |
| Histori GRN | app/Http/Controllers/PurchaseReceiptController.php | Memerlukan company ownership aktif; controller index scoped. Link sidebar kini mengikuti prasyarat flag. Konteks/membership tetap diperiksa backend. |
| Proteksi GRN | app/Support/GrnProtection.php | Bill/PO/jurnal posted dilindungi dari penghapusan/koreksi legacy. Reversal GRN belum tersedia. |
| Bill manual | app/Http/Controllers/PurchaseBillController.php: store | Pembuatan Bill langsung menjurnal, opsional memasukkan stok; belum pemisahan draft/approval/posting. |
| Pembatalan Bill | PurchaseBillController.php: destroy | Bill non-GRN dapat dihapus bersama jurnal dan pembalikan stok. Belum cancellation via jurnal reversal dengan histori utuh. |
| Payment aktif | app/Http/Controllers/PaymentPlanController.php | Alur aktif di controller. app/Services/PaymentPlanService.php ditandai deprecated/orphan; jangan dijadikan dasar implementasi. |
| Status payment | PaymentPlanController.php: updateStatus | Enum divalidasi tetapi method tidak membatasi transisi dari status awal atau maker-checker; POSTED dapat dipilih tanpa posting melalui method ini. |
| Posting payment | PaymentPlanController.php: postJournal | ID jurnal deterministik dan cek duplikat tersedia. Guard status hanya melewati POSTED; tidak mensyaratkan APPROVED/PAID di method yang diperiksa. Pembacaan dilakukan sebelum transaksi tanpa lock row payment. |
| Pelunasan Bill | PaymentPlanController.php: postJournal | ref_bill_number diwajibkan untuk kategori hutang; Bill yang ditemukan ditandai PAID tanpa perhitungan saldo/alokasi parsial pada method ini. Jika Bill tidak ditemukan, posting tidak dibatalkan oleh blok tersebut. |
| Hapus payment | PaymentPlanController.php: destroy | Lookup memakai evidence_number = JRN-{no_transaksi}, sedangkan posting baru menyimpan source_doc_no dan bukti internal PP. Berisiko meninggalkan jurnal saat payment dihapus. Turunan PO/file juga dihapus; bukan reversal. |
| Otorisasi | routes/web.php, bootstrap/app.php, method payment terkait | Route payment berada dalam auth; tidak terlihat permission per aksi di method yang diperiksa. Ini bukan audit lengkap semua middleware/model/policy. |

## Prioritas dan urutan backlog

### P0: pengamanan Payment Plan (lingkup berikut, belum diimplementasikan)

1. Tetapkan kebijakan: larang hard-delete PAID/POSTED dan PO yang sudah receiving;
   koreksi dilakukan lewat reversal berotorisasi. Jangan sekadar mengganti lookup
   jurnal lalu memperluas penghapusan data finansial.
2. Pisahkan status administratif dari kejadian finansial: updateStatus tidak boleh
   membuat POSTED tanpa jurnal atau menurunkan status jurnal posted.
3. Tetapkan role per aksi dan transisi yang disahkan Finance/manajemen.
4. Posting: transaction + lock row + recheck idempotensi/status/account/date/amount.
5. Validasi Bill, vendor dan saldo; definisikan partial payment, overpayment,
   advance, serta aturan alokasi. Jangan otomatis PAID untuk pembayaran sebagian.

Acceptance test yang perlu ditulis: forbidden transition, unauthorized action,
posted delete blocked, received PO preserved, Bill missing/wrong vendor rejected,
partial payment tidak PAID, retry/concurrency tidak menjurnal dua kali, nominal
desimal dan rollback utuh. Concurrency perlu MySQL database test khusus yang
disetujui operator; SQLite bukan bukti validasi lockForUpdate produksi.

### P1: workflow Purchase dan matching

1. Discovery PR dan approval lintas modul; jangan menyimpulkan tidak ada hanya
   dari route web.php yang diperiksa.
2. Putuskan PO draft → submit → approval sebelum mengganti default APPROVED.
3. Putuskan apakah GRN mem-posting AP langsung (compatibility saat ini) atau GRNI
   lalu AP invoice terpisah. Wajib mapping COA/kebijakan cutover disahkan Finance.
4. Rancang matching qty/harga/pajak dan hubungan banyak receipt ke invoice.
5. Rancang reversal receipt/Bill yang mempertahankan event dan jurnal asal.

### P2: perluasan dan laporan

Rekonsiliasi AP/GL/payment history, approval matrix berversi, audit trail perubahan,
period lock, laporan outstanding dan export. Tidak mendahului pengamanan P0.

## Keputusan yang masih diperlukan

- Role maker/reviewer/approver/poster dan limit nominal.
- Status yang diizinkan untuk posting dan koreksi dokumen historis.
- Partial payment/overpayment/advance, vendor identity, mata uang dan kurs.
- AP langsung saat GRN vs GRNI; tax/discount dan cutover legacy.
- Kebijakan cancel/reversal serta periode terkunci.

## Validasi dan batas perubahan saat audit

Perbaikan langsung hanya guard tampilan link GRN dan regression test konfigurasi.
Tidak mengubah PaymentPlanController, PurchaseBillController, service receiving,
schema, feature flag deployment, approval, akun, maupun data historis.
UAT browser desktop/mobile dan Google Translate tetap memerlukan operator.
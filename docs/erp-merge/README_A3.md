# A3 — GRN kompatibel dengan receiving baseline MGI

Tanggal paket: 25 September 2026. Baseline adalah source A2 Activated yang diunggah operator, SHA-256 ZIP:
`020d5346b0b51e313f84f1b9c688df92edd7e8a4e2a9f1ed217a6b17929f410f`.
Tiga perbaikan A2 yang diuji di XAMPP tetap dipertahankan tanpa perubahan.

## Status paket

Source dan pengujian disiapkan; **belum diinstal atau dijalankan pada XAMPP operator**. PHP, Composer, MySQL, dan PowerShell tidak tersedia pada lingkungan penyusun. Tidak ada klaim PHP lint/PHPUnit lulus. Pemeriksaan lokal hanya hash, struktur arsip, scope diff, dan penerapan patch ke baseline yang telah diverifikasi.

`PLATFORM_GRN_ENABLED` default **false**. Pengujian runtime wajib dilakukan sebelum aktivasi. Paket tidak mengubah `.env` atau database aktif. Ini tetap ERP satu perusahaan MGI, bukan multi-company penuh.

## Perilaku yang dibangun

- Form penerimaan PO yang sudah ada dan tab penerimaan pembelian gudang mengirim `context_company_id` dan UUID `request_key`.
- Satu penerimaan menghasilkan satu GRN, satu Bill baru, detail GRN, dan satu jurnal yang sama dengan receiving lama. Tidak ada posting GRN kedua.
- Semua perubahan berada dalam satu transaksi luar. Kegagalan setelah stok/jurnal ditulis harus membatalkan GRN, Bill, detail, qty PO, stok, kartu stok, journal, dan binding jalur PO.
- GRN memakai nomor `GRN-<UUID>`; daftar/detail tersedia pada `/purchase-receipts`. PO, Bill, ID jurnal dan detail barang dapat ditelusuri dari halaman detail.
- Company MGI dikunci sebelum PO dan produk untuk menserialkan posting GRN, termasuk dua request dengan PO berbeda. Throughput awal dibatasi satu transaksi GRN per company pada satu waktu.
- Kombinasi company + request key unik. Payload sama mengembalikan ID GRN yang sama, termasuk sesudah PO selesai. Key sama dengan payload berbeda ditolak. Nomor Bill yang sudah ada di Bill, jurnal, atau ledger ditolak.
- Kuantitas harus bilangan bulat nonnegatif, ada setidaknya satu qty positif, dan tidak melebihi sisa PO. Detail harus milik PO. SKU harus ditemukan pada master produk dan link product harus konsisten. Harga harus positif. Produk tidak dibuat otomatis.
- Pemilik PO dan membership tetap diperiksa melalui A2. Payload company_id ditolak. Akun persediaan dan hutang harus ada dan berbeda; COA tidak diubah/dibuat.

## Batas bisnis V1 — penting sebelum penggunaan

1. **Hanya pembelian hutang tanpa uang muka, pajak, atau diskon.** PO dengan fitur tersebut ditolak sebelum posting, karena alokasi Bill/GRN belum didefinisikan. Nilai dan waktu pengakuan untuk kasus yang didukung sama dengan baseline: debit persediaan, kredit hutang ketika diterima.
2. **Satu GRN = satu Bill baru.** Dua pengiriman 40+60 memakai dua Bill berbeda. Bill existing tidak ditautkan otomatis. Jika satu faktur supplier mencakup beberapa pengiriman, jangan mengarang nomor faktur supaya lolos; diperlukan tahap alokasi banyak GRN ke satu Bill.
3. GRN tidak memisahkan receiving dan billing secara akuntansi; tidak menambah akun GRNI atau mengubah mapping akun.
4. Tidak ada editor draft atau approval GRN terpisah. Status DRAFT hanya sementara di transaksi database; sukses menjadi POSTED, gagal tidak meninggalkan dokumen. Akses mengikuti akses transaksi legacy + membership MGI, bukan permission GRN baru.
5. **Reversal GRN belum tersedia.** GRN posted tidak boleh diedit/dihapus dengan void legacy. Jangan menghapus jurnal untuk koreksi. Reversal yang menjaga audit dan aturan periode perlu tahap berikutnya.
6. Tidak menambahkan sistem tutup periode akuntansi, alokasi pajak/selisih harga, settlement uang muka, atau Payment Request. Tidak mengklaim kasus itu teruji/terselesaikan.
7. SQL operator langsung dapat melewati pemeriksaan aplikasi. Foreign key melindungi penghapusan referensi tertentu, bukan pengganti kontrol akses database atau immutability seluruh database.

## Jalur dokumen yang menetap

Migration baru menambahkan `purchase_orders.receipt_mode`:

- Penerimaan pertama saat flag ON mengikat PO ke `GRN_V1`.
- Penerimaan lewat jalur lama saat flag OFF mengikat PO ke `LEGACY` bila migration A3 sudah terpasang.
- PO GRN tetap menggunakan GRN jika flag kemudian OFF. Flag hanya menentukan penerimaan baru, bukan memindahkan riwayat yang sudah diproses.
- PO LEGACY atau PO dengan qty_received lama tanpa GRN ditolak oleh jalur GRN; tidak ada backfill/konversi otomatis.
- Void lama, penggantian PO via import, dan Bill delete ditolak untuk dokumen GRN. FK restrict menjaga PO, detail PO, produk, company, Bill, dan header jurnal yang direferensikan.
- Perubahan/penghapusan model jurnal, bulk delete jurnal, serta empat jalur import/overwrite jurnal yang ditemukan diberi guard. InventorySyncService menolak posting/reverse dengan evidence Bill GRN yang sudah posted.

Body kalkulasi receiving lama dipertahankan dan dipanggil sekali melalui callback internal setelah Bill/GRN dicadangkan. `PostingService`, `config/coa.php`, model Account/User, composer dependencies dan migration A2 tidak diubah. PurchaseBill model/controller/view diselaraskan ke kolom schema nyata `bill_date`, `vendor_name`, `due_date`, `credit_account`, dan `notes` agar Bill hasil GRN dapat ditampilkan; rumus Bill manual tidak diubah.

## Instalasi source

Ekstrak ZIP ke staging di luar repository. Baca `CHANGED_FILES_A3.json`; cocokkan baseline SHA-256 setiap modified file sebelum menyalin. File added yang sudah ada juga harus dibandingkan. Result hash yang sudah sama berarti already applied. Bila berbeda, baca/merge perubahan lokal dan catat deviasinya; jangan overwrite seluruh ZIP.

Salin hanya `files` dan `metadata_files`. Metadata manifest tidak menyimpan hash dirinya sendiri. Alternatif tersedia `A3_CHANGES.patch`; gunakan `git apply --check` sebelum apply, jangan sekaligus copy manifest dan apply patch.

Arsip source tidak membawa `.env`, vendor, storage, bootstrap/cache, SQL atau backup. Repository XAMPP harus mempertahankan direktori runtime yang sudah ada. Jangan reset/clean working tree atau `git add .`; A1/A2 belum di-commit menurut laporan operator.

## Gate pengujian

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1 -FullSuite
powershell -NoProfile -ExecutionPolicy Bypass -File tools/verify-platform-a3.ps1 -MySqlIntegration
```

Verifier melakukan lint PHP manifest, mengisolasi SQLite `:memory:`, array cache/session, queue sync, config cache sementara, serta direktori compiled Blade sementara agar timestamp ZIP tidak memakai Blade lama. Environment process dipulihkan sesudah selesai.

- 16 skenario service bersama dijalankan pada SQLite dan MySQL: parsial 40+60; retry; konflik payload/Bill; overreceipt; input negatif/pecahan/nol; detail asing; SKU hilang; diskon; rollback sesudah posting; routing saat flag off; binding legacy; journal protection; FK restrict; kesamaan nominal dengan legacy; stock sync guard.
- 4 test fitur tambahan memeriksa konteks HTTP, halaman GRN/Bill, membership dicabut, Bill delete, import PO, serta token kedua form.
- 3 test MySQL tambahan memeriksa readiness, dua proses PHP dengan request identik, serta dua proses qty60+60 terhadap PO100. Seluruh test harus lulus dengan assertion asli yang bermakna.

MySQL gate membuat **database fixture baru** `mgi_fresh_a3test_<16 hex>` menggunakan credential test `MGI_TEST_HOST/PORT/USER/PASSWORD` (fallback lokal root tanpa password). Suite menjalankan migration chain lengkap HANYA pada fixture itu untuk menguji schema nyata, lalu drop fixture yang dibuat sendiri. Dua child process menunjuk fixture yang sama melalui environment khusus. Tidak menggunakan `mgi_fresh_20260924` atau `db_akuntansi`. Bila migration chain MySQL gagal, jangan menggantinya dengan database kerja; diagnosis dan laporkan gate yang gagal.

Catat jumlah test/assertion aktual; jumlah di atas adalah skenario yang disiapkan, **bukan hasil eksekusi**. Tes concurrency tidak dijamin oleh SQLite; MySQL gate wajib.

## Aktivasi terkontrol setelah seluruh gate lulus

Baseline runtime yang dilaporkan: `mgi_fresh_20260924`, satu company 1/MGI, A2 ON, legacy sync/CEISA false, COA208, users3, Party grant hanya Darwin. Verifikasi ulang, termasuk perubahan data sejak laporan terakhir.

1. Pastikan batas V1 sesuai kasus yang hendak dipakai. Jika operasi memerlukan pajak/uang muka atau satu Bill untuk beberapa GRN, pasang dan uji source tetapi **biarkan flag OFF**; laporkan kebutuhan itu untuk tahap lanjutan.
2. Verifikasi runtime database dan DB_URL sebelum menulis. Masuk maintenance, pastikan worker/scheduler/import tidak aktif, hentikan penulis lain.
3. Backup **MGI saat ini** dan `.env` di `D:\ERP_Backup` menggunakan nama baru bertimestamp serta label `PRE_A3`. Jangan menimpa backup BBW/A2. Verifikasi hash dan restore-test fixture terpisah.
4. Untuk aktivasi pertama pada fresh MGI ini, pastikan PO/SO, GRN, Bill, jurnal dan stok bisnis masih kosong. Bila sudah ada transaksi, berhenti pada review routing/schema/data tanpa reset atau backfill. Jangan menganggap pemeriksaan nol kemarin masih berlaku.
5. Jalankan hanya migration A3 berikut pada target yang sudah diverifikasi:

```powershell
php artisan migrate --path=database/migrations/2026_09_25_090001_add_grn_posting_contract.php --force
php artisan platform:check-order-ownership
php artisan platform:check-grn
```

Jangan menjalankan migration pending lain. MySQL DDL tidak atomic: jika migration gagal sebagian, pertahankan maintenance dan periksa schema sebenarnya; jangan mengulang membabi buta atau menghapus riwayat migration.

6. Readiness harus PASSED. Command GRN read-only memeriksa schema, A2/MGI, mapping akun, linkage/nilai GRN-Bill-jurnal dan rekonsiliasi qty PO untuk dokumen GRN. Ini bukan bukti lengkap bahwa seluruh ERP seimbang atau bahwa tutup periode diterapkan.
7. Hanya setelah gate dan batas penggunaan di atas terpenuhi, set satu key `.env`:

```dotenv
PLATFORM_GRN_ENABLED=true
```

Pertahankan `PLATFORM_ORDER_COMPANY_SCOPE_ENABLED=true`, legacy sync false, CEISA false, APP_KEY, credential, database dan backend session/cache/queue.

```powershell
php artisan config:clear
php artisan view:clear
php artisan platform:check-grn
php artisan view:cache
php artisan view:clear
```

Periksa nilai runtime DB/flag, hanya +1 migration dari baseline aktual, COA/users/grant tidak berubah, dan bisnis tetap kosong. Buka aplikasi dengan `php artisan up` dan restart dev server bila diperlukan. Worker/scheduler tetap berhenti.

8. Smoke baca login DARWIN, company MGI, daftar GRN, PO receiving modal, penerimaan gudang, dan daftar Bill. Jangan membuat transaksi dummy di database kerja, mereset password, atau memberi grant baru. Jika kredensial interaktif tidak tersedia, laporkan browser terotorisasi belum diuji.

## Rollback dan tindak lanjut

Sebelum ada dokumen GRN, deployment gagal dapat ditinjau dengan backup dan schema aktual. Sesudah GRN ada, jangan drop kolom/migration, restore backup lama secara sepihak, memindahkan ke BBW, atau mematikan A2. `down()` A3 sengaja menolak ketika riwayat GRN ada. Flag GRN OFF tidak menonaktifkan perlindungan dokumen existing.

Tahap berikutnya setelah V1 teruji adalah reversal yang terkait sumber, aturan periode akuntansi, serta alokasi GRN–Bill untuk pajak/uang muka atau beberapa penerimaan dalam satu tagihan. Payment Request dibangun setelah outstanding Bill dan kebijakan settlement jelas.

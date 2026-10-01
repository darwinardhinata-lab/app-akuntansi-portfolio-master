# Audit Rilis Main — 1 Oktober 2026

## 1. Ruang Lingkup

Audit dilakukan terhadap perubahan working tree sebelum rilis ke branch `main`.
Cakupan utama:

- pemisahan **No. Bukti internal** dan **No. Transaksi/dokumen fisik**;
- hyperlink Jurnal Umum/Buku Besar ke dokumen sumber dan edit jurnal;
- input nominal jurnal presisi dua desimal;
- kompatibilitas skema database legacy untuk resolver hyperlink;
- pengaman GRN, import ulang, dan void penerimaan;
- penghilangan toolbar/notifikasi Google Translate tanpa menonaktifkan terjemahan;
- pengaman database operasional dan dokumentasi recovery.

## 2. Keputusan Data dan Nomor Dokumen

| Data | Kolom | Kontrak |
|---|---|---|
| No. Bukti | `journal_headers.evidence_number` | Kunci internal otomatis: `{KODE}-{YYYYMMDD}-{0000}`. |
| No. Transaksi | `journal_headers.source_doc_no` | Nomor dokumen fisik/asli, Bill, Invoice, PO, SPK, MRN, atau nomor import. |
| ID Jurnal | `journal_headers.journal_id` | Primary key teknis; bukan nomor bisnis utama. |

Kode No. Bukti yang diimplementasikan: `GJ`, `IMP`, `INV`, `BIL`, `GRN`, `SPK`, `MRN`, `SR`, `PR`, `PP`, dan `OUT`.

Generator memakai transaksi database dan `lockForUpdate()` untuk urutan per kode/tanggal.
Data historis tidak dimigrasikan atau dinomori ulang secara otomatis.

## 3. Temuan Audit dan Tindakan

| Prioritas | Temuan | Tindakan rilis | Status |
|---|---|---|---|
| Kritis | Resolver hyperlink meng-query `sales_returns.journal_id`, tetapi kolom belum ada pada database operasional. | Resolver kini memeriksa `Schema::hasColumn()` sebelum query relasi `journal_id`; fallback aman tetap tersedia. | Ditutup |
| Tinggi | Perubahan nomor menyebabkan void penerimaan PO merujuk variabel `$journalIds` tanpa assignment. | Assignment dipulihkan dan lookup memakai `source_doc_no`. | Ditutup |
| Tinggi | Proteksi GRN membandingkan Bill fisik terhadap No. Bukti internal baru. | Proteksi kini memprioritaskan `source_doc_no` sebagai nomor Bill/dokumen fisik. | Ditutup |
| Tinggi | Import ulang berpotensi menggunakan No. Bukti baru sebagai key duplikasi. | Idempotensi import dan cleanup memakai `journal_type=IMPORT` + `source_doc_no`. | Ditutup |
| Sedang | Pop-up detail jurnal dapat mencampur detail jika nomor bukti sama. | Endpoint AJAX dibatasi berdasarkan `journal_id`. | Ditutup |
| Sedang | Banner Google Translate tetap muncul setelah penerjemahan asinkron. | CSS, `MutationObserver`, dan cleanup iframe aktif diterapkan; engine Google Translate tetap berjalan. | Ditutup |
| Sedang | Dump SQL dan output audit lokal tidak memiliki aturan ignore eksplisit. | `backup_*.sql`, output audit, dan output test lokal ditambahkan ke `.gitignore`. | Ditutup |

## 4. Validasi yang Dijalankan

### Syntax dan view

- Pemeriksaan lint PHP pada file yang diubah: lulus.
- `php artisan view:cache`: lulus.
- Cache view dibersihkan kembali setelah validasi.

### Automated tests

Perintah:

```powershell
php artisan test --no-ansi
```

Hasil akhir:

```text
Tests: 233 passed (9064 assertions)
Duration: 17.94s
```

Test mencakup regresi No. Bukti/No. Transaksi, import ulang, GRN, void penerimaan PO, stitching manufaktur, hyperlink jurnal/buku besar, login, dan katalog terjemahan.

### Pemeriksaan repository

- Tidak ada kredensial literal ditemukan pada source aplikasi melalui pemindaian pola password/API key/secret.
- `.env` tetap di-ignore dan tidak dimasukkan ke commit.
- Dump database dan output test/audit lokal dikecualikan dari commit.
- `git diff --check` masih melaporkan trailing whitespace lama pada `app/Services/JournalCsvImportService.php` baris 170 dan 178. Temuan ini tidak memengaruhi runtime; harus dibersihkan pada perapian terpisah bila ingin zero-warning diff.

## 5. Status Migration

Database lokal memiliki sejumlah migration Customs Reports yang masih `Pending`. Perubahan rilis ini tidak menjalankan migration tersebut secara otomatis.

Sebelum deployment, operator harus meninjau migration pending dan menjalankan hanya migration yang telah disetujui melalui prosedur operasional database. Jangan menggunakan command destruktif pada database `mgi_fresh_*`.

## 6. Risiko Residual dan Rekomendasi

1. Beberapa tabel legacy (misalnya `sales_returns`) belum memiliki `journal_id`; resolver sudah kompatibel tetapi menggunakan fallback sampai migration relasi disetujui.
2. Data historis belum diberi ulang No. Bukti internal. Penomoran baru berlaku untuk data baru agar audit trail historis tidak berubah.
3. Toolbar Google Translate dapat berasal dari extension browser. Jika tetap muncul setelah hard refresh, nonaktifkan extension Translate untuk domain lokal; kode aplikasi hanya dapat menghapus banner yang disuntikkan oleh script Google di halaman.
4. Lengkapi matriks limit dan otorisasi pada SOP Payment Plan sebelum diberlakukan sebagai kebijakan final.
# PAYMENT PLAN FIX SUMMARY
**Tanggal:** 4 Agustus 2026  
**Berdasarkan:** Analisis Mendalam Modul Payment Plan + Feedback Jubelio Flow

---

## Masalah yang Ditemukan

1. **`PaymentPlanService::postToJournal()` — orphan code, fatal bug**: Mengkredit **Piutang Usaha** (aset) untuk transaksi kas keluar, dan menulis ke kolom `purchase_orders` yang tidak ada di skema. Tidak pernah dipanggil, tapi berbahaya jika suatu saat aktif.
2. **`postJournal()` (controller) — akun debit bebas untuk PEMBAYARAN HUTANG**: Staff bisa pilih akun bebas via `setCoa()`, sehingga saat posting hutang, akun yang ter-debit bisa jadi salah (bukan Hutang Usaha).
3. **`postJournal()` — tidak update `purchase_bills.payment_status`**: Setelah pelunasan hutang diposting, status BIL tetap `UNPAID` selamanya. Laporan aging hutang tidak sinkron.
4. **`store()` — membuat PO sintetis untuk Uang Muka**: `PO-{no_transaksi}` bukan PO asli Jubelio. Saat barang diterima di PO asli, sistem gagal mencocokkan uang muka → uang muka "nyangkut", hutang tercatat penuh.
5. **`receivePartialOrder()` — matching uang muka hanya by synthetic PO**: Tidak ada fallback ke PO asli.
6. **Tidak ada validasi `ref_bill_number` untuk PEMBAYARAN HUTANG**: Bisa POSTED tanpa referensi bill.
7. **Tidak ada kolom referensi** antara `transaksi_payment_plan` dan `purchase_bills` / `purchase_orders`.
8. **Form tidak terstruktur seperti Jubelio**: Tidak ada cascade dropdown Vendor → Bill/PO.

---

## Fix yang Diterapkan

### Fix #1: Tambah kolom referensi (Migration)
**File:** `database/migrations/2026_08_04_000002_add_payment_plan_reference_columns.php`  
- `ref_bill_number` — untuk kategori PEMBAYARAN HUTANG, link ke `purchase_bills.bill_number`
- `ref_po_number` — untuk kategori UANG MUKA, link ke `purchase_orders.po_number` (PO asli Jubelio)

### Fix #2: Update Model
**File:** `app/Models/PaymentPlan.php`  
- Tambah `ref_bill_number`, `ref_po_number`, `bukti_file` ke `$fillable`

### Fix #3: Fix `postJournal()` — Akun Debet & Update Status Bill
**File:** `app/Http/Controllers/PaymentPlanController.php`  
- Jika kategori = PEMBAYARAN HUTANG: **paksa** debit = `config('coa.hutang_usaha')` (tidak peduli apa yang dipilih staff)
- Validasi: `ref_bill_number` wajib diisi untuk PEMBAYARAN HUTANG
- Setelah posting sukses → update `purchase_bills.payment_status = 'PAID'`

### Fix #4: Fix `store()` — PO Asli vs Sintetis
**File:** `app/Http/Controllers/PaymentPlanController.php`  
- Baca `ref_po_number` dari form
- Cari PO asli di `purchase_orders`. Jika ada → pakai PO itu
- Jika tidak ada → fallback ke sintetis `PO-{no_transaksi}` (backward compatible)
- Jika PO asli dipakai → update `ref_po_number` di payment plan

### Fix #5: Fix `receivePartialOrder()` — Matching Uang Muka
**File:** `app/Services/PurchaseOrderService.php`  
- Tambah fallback: cek `transaksi_payment_plan` by `ref_po_number = po.po_number`
- Jika tidak ada, fallback ke logic lama (synthetic PO)

### Fix #6: Bekukan `PaymentPlanService`
**File:** `app/Services/PaymentPlanService.php`  
- Tambah deprecation notice di docblock
- Tidak dihapus (referensi sejarah), tapi jelas tidak boleh dipakai

### Fix #7: Validasi Kategori
**File:** `app/Http/Controllers/PaymentPlanController.php` (dalam `postJournal()`)  
- `PEMBAYARAN HUTANG` tanpa `ref_bill_number` → skip dengan pesan error

### Fix #8: Cascading Dropdown seperti Jubelio
**Files:** 
- `app/Http/Controllers/PaymentPlanController.php` — tambah `apiBillsByVendor()` dan `apiPOsByVendor()`
- `resources/views/payment_plan/create.blade.php` — redesign form dengan cascade:
  - PEMBAYARAN HUTANG: Vendor input → Select2 AJAX Bills by vendor → auto-fill nominal/keterangan/ref_bill_number
  - UANG MUKA: Vendor input → Select2 AJAX POs by vendor → auto-fill nominal/keterangan/ref_po_number + show PO detail table
- `routes/web.php` — register `/payment-plan/api/bills-by-vendor` dan `/payment-plan/api/pos-by-vendor`

---

## File yang Dimodifikasi

| # | File | Perubahan |
|---|---|---|
| 1 | `database/migrations/2026_08_04_000002_...php` | **Baru**: kolom ref_bill_number + ref_po_number |
| 2 | `app/Models/PaymentPlan.php` | +3 field ke fillable |
| 3 | `app/Http/Controllers/PaymentPlanController.php` | postJournal paksa Hutang Usaha + update bill status; store pakai real PO; +2 API endpoints |
| 4 | `app/Services/PurchaseOrderService.php` | receivePartialOrder fallback ke ref_po_number |
| 5 | `app/Services/PaymentPlanService.php` | Deprecation notice |
| 6 | `resources/views/payment_plan/create.blade.php` | Cascade dropdown Vendor → Bill/PO dengan Select2 AJAX |
| 7 | `routes/web.php` | +2 routes untuk API cascading dropdowns |

---

## Langkah Manual

1. `php artisan migrate` — jalankan migration untuk tambah kolom referensi
2. **Backfill data lama**: untuk Payment Plan lama yang sudah ada, isi `ref_bill_number` dan `ref_po_number` secara manual atau via script update
3. Verifikasi: pastikan semua `PEMBAYARAN HUTANG` yang sudah POSTED memiliki `ref_bill_number`
4. Verifikasi: pastikan semua Uang Muka memiliki `ref_po_number` yang menunjuk ke PO asli Jubelio
5. Test: buat Payment Plan PEMBAYARAN HUTANG baru, pilih vendor → pilih bill → posting → cek apakah `purchase_bills.payment_status` berubah ke `PAID`
6. Test: buat Payment Plan UANG MUKA, pilih vendor → pilih PO → cek apakah PO yang dipakai adalah PO asli (bukan sintetis)

---

## Catatan

- Semua proteksi anti-dobel yang ada di `postJournal()` tetap dipertahankan (ID deterministik + cek status POSTED).
- Perubahan ini **backward compatible**: jika `ref_po_number` belum diisi, sistem tetap fallback ke synthetic PO seperti semula.
- Cascade dropdown menggunakan Select2 AJAX, pattern yang sama dengan PurchaseBill controller (`getPosBySupplier`).
- `payment:audit` command tidak dapat dibuat karena editor ditutup. Audit query sudah dijelaskan di file ini, bisa dijalankan manual via SQL.
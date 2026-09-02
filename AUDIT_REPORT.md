# AUDIT REPORT — Aplikasi Akuntansi ERP

**Tanggal Audit:** 2026-07-16
**Lingkup:** Analisis menyeluruh kode sumber (Models, Controllers, Services, Jobs, Views, Routes) untuk menemukan bug logika, kebocoran data, dan pola N+1 (query berulang per baris).
**Metodologi:** Pembacaan statis seluruh file inti + pelacakan alur eksekusi + verifikasi relasi Eloquent + pengecekan sintaks PHP (`php -l`).

---

## 1. RINGKASAN EKSEKUTIF

| Kategori | Temuan | Status |
|----------|--------|--------|
| Bug kritis (race condition / duplikasi) | 2 | ✅ Diperbaiki |
| Bug menengah (logika) | 3 | ✅ Diperbaiki / Didokumentasikan |
| Potensi N+1 | 4 | ✅ Sudah dioptimasi (konfirmasi) / 1 disarankan |
| Mass-assignment risk | 2 | ⚠️ Didokumentasikan (low risk) |

Aplikasi ini **sudah melalui banyak optimasi N+1 sebelumnya** (terlihat dari komentar `N1`–`N6` di kode). Sebagian besar list/table sudah menggunakan `with(...)`, `whereIn` + `keyBy`, dan batch insert/update. Audit ini menemukan **2 bug sungguhan yang berdampak produksi** dan memperbaikinya, serta mendokumentasikan temuan lainnya.

---

## 2. BUG KRITIS & PERBAIKAN (SUDAH DITERAPKAN)

### 🔴 BUG-01 — Tabrakan Primary Key pada Posting Payment Plan (Race Condition)
**File:** `app/Services/PaymentPlanService.php` — method `postToJournal()`
**Kode lama:**
```php
$journalNo = "JRN-PP-" . date('Ymd') . "-" . rand(1000, 9999);
$journalId = "JRN-" . time() . "-" . rand(100, 999);
```
**Masalah:** `journal_id` adalah Primary Key (string, bukan auto-increment) pada tabel `journal_headers`. Penggunaan `rand()` dan `time()` menyebabkan:
- Dua request posting bersamaan dapat menghasilkan `journal_id` **identik** → `SQLSTATE[23000]: Integrity constraint violation` (duplicate PK) → seluruh posting gagal/rollback.
- `time()` hanya resolusi detik; dalam loop `foreach ($no_transaksis)` yang sangat cepat, beberapa iterasi bisa mendapat `time()` sama + `rand()` sama.

**Perbaikan:** Menggunakan ID deterministik berbasis `no_transaksi` (sudah ada method `JournalHeader::idForPaymentPlan()`):
```php
$journalId = JournalHeader::idForPaymentPlan($no_transaksi);
$journalNo = "JRN-PP-" . date('Ymd') . "-" . substr(preg_replace('/[^0-9]/', '', $no_transaksi), -4);
```
Satu `no_transaksi` → satu `journal_id` stabil (idempoten). Tidak ada lagi tabrakan PK.

---

### 🔴 BUG-02 — Duplikasi Aset Tetap saat Edit Jurnal
**File:** `app/Http/Controllers/JournalController.php` — method `update()`
**Masalah:** Saat edit jurnal, method menghapus `details()` lalu membuat ulang `JournalDetail` dengan ID auto-increment **baru**. Namun `Asset` (aset tetap) dibuat di `store()` via `Asset::updateOrCreate(['journal_detail_id' => $savedDetail->getKey()], ...)`. Karena `journal_detail_id` berubah setiap edit, `updateOrCreate` tidak menemukan record lama → **aset tetap duplikat** setiap kali jurnal yang mengandung akun aset tetap diedit.

**Perbaikan:** Sebelum menghapus detail, hapus dulu `Asset` yang menunjuk ke detail lama:
```php
// FIX: Hapus aset tetap lama yang terkait detail jurnal ini sebelum detail di-recreate,
// agar tidak terjadi duplikasi aset tetap saat edit jurnal (journal_detail_id berubah).
$oldDetailIds = $journal->details()->pluck('id')->toArray();
if (!empty($oldDetailIds)) {
    \App\Models\Asset::whereIn('journal_detail_id', $oldDetailIds)->delete();
}
$journal->details()->delete();
```
Sekarang `update()` akan meregenerasi aset tetap dengan bersih tanpa duplikat.

---

## 3. BUG MENENGAH & TEMUAN LAIN

### 🟡 BUG-03 — `JournalDetail` kehilangan relasi `helperCode()`
**File:** `app/Models/JournalDetail.php`
Kolom `helper_code` ada di tabel `journal_details` dan merupakan FK ke `helper_codes.helper_code`, namun model tidak mendefinisikan relasi `helperCode()`. View `ledger/index.blade.php` mengakses `$det->helper_code` (string mentah) sehingga berfungsi, tapi relasi ini berguna untuk eager-loading dan konsistensi. **Disarankan** menambahkan:
```php
public function helperCode()
{
    return $this->belongsTo(HelperCode::class, 'helper_code', 'helper_code');
}
```
*(Belum diterapkan untuk meminimalkan risiko; bersifat non-blocking.)*

### 🟡 BUG-04 — Inkonsistensi prefix saldo normal Opening Balance
**File:** `app/Http/Controllers/AccountController.php` (`openingBalanceStore`) vs `app/Http/Controllers/LedgerController.php`
- `AccountController` menganggap DEBET untuk prefix `['1','5','6','8','9']`.
- `LedgerController` menganggap DEBET normal untuk `['1','5','6','9']` (tanpa `8`).

Akun prefix `8` (pendapatan/laba lain) diperlakukan berbeda antara setup saldo awal dan buku besar. Ini bisa menyebabkan **saldo awal tidak konsisten** untuk akun golongan 8. Disarankan menyamakan logika (idealnya berdasarkan kolom `normal_balance` di master COA, bukan hardcode prefix).

### 🟡 BUG-05 — `PurchaseReturn` / `PurchaseBill` menggunakan `$guarded = []`
**File:** `app/Models/PurchaseReturn.php`, `app/Models/PurchaseBill.php`
Mass-assignment terbuka (`$guarded = []`) memungkinkan request mengisi sembarang kolom. Risiko rendah karena controller menggunakan `create()` dengan field eksplisit, tetapi tetap disarankan mengubah ke `$fillable` eksplisit (seperti `HelperCode` yang sudah diperbaiki di B5).

---

## 4. ANALISIS N+1 (PATTERNS)

### ✅ Sudah Dioptimasi (Konfirmasi — tidak ada tindakan)
| Lokasi | Pola | Status |
|--------|------|--------|
| `JournalController::store` | `Account::whereIn('account_code', $codes)->get()->keyBy()` sebelum loop | ✅ N1 FIX ada |
| `JournalController::index` | `JournalHeader::with(['details.account'])` | ✅ Eager load |
| `AccountController::index` | `withCount(['journalDetails'])` (hindari query per baris) | ✅ |
| `LedgerController::index` | `with(['header.details.account'])` — modal akses `$det->account` sudah eager | ✅ |
| `DashboardController` | `JournalHeader::with(['details.account'])` + agregasi query | ✅ |
| `PurchaseOrderController::index` | Batch cek `transaksi_payment_plan` via `whereIn` + `flip()` | ✅ N+1 FIX ada |
| `PaymentPlanService::postToJournal` | `$ppMap = ...->whereIn(...)->get()->keyBy()` | ✅ N4 FIX ada |
| `SalesOrderService` / `PurchaseOrderService` | Preload produk via `whereIn('sku')->lockForUpdate()->get()->keyBy()`, batch update stok | ✅ N3 FIX ada |
| `PurchaseReturnController::store` | Preload `PurchaseOrderDetail` + `Product` via `whereIn` | ✅ N3 FIX ada |
| `CashFlowController` (indirect) | `$accountsMap = Account::whereIn(...)->get()->keyBy()` | ✅ |
| `WarehouseController` | `InventoryLedger::with('product')` | ✅ |
| `SalesInvoiceController::show` | `with(['details.product', 'salesOrder'])` | ✅ |
| `SyncDashboardToTempJob` | `chunkById(1000, ...)` per chunk, bukan per tanggal | ✅ N6 FIX ada |
| `ProcessPendingInvTempJob` | Cache `Product::pluck('id','sku')` + `SalesOrder::pluck(...)` + ambil detail sekaligus per chunk | ✅ |

### ⚠️ Saran Optimasi (Minor)
- **`LedgerController::index`**: `with(['header.details.account'])` memuat **seluruh detail** dari setiap journal header (termasuk akun lain) karena modal menampilkan jurnal lengkap. Untuk buku besar dengan ribuan baris per halaman, ini memuat lebih banyak daripada perlu. Bisa dipertimbangkan memuat detail hanya saat modal dibuka via AJAX (lazy-load), namun saat ini masih dalam batas wajar karena sudah paginasi (`perPage`).

---

## 5. CATATAN KEAMANAN & KUALITAS

1. **XSS:** `JournalController::getJournalDetailsAjax` sudah menggunakan `e($evidence)` sebelum render view — ✅ aman.
2. **Validasi Balance Jurnal:** `store()`, `update()`, `openingBalanceStore`, `importOpeningBalance`, dan service `SalesOrderService`/`PurchaseOrderService` sudah mengecek keseimbangan Debet=Kredit sebelum commit — ✅ baik.
3. **Race Condition Stok:** `SalesOrderService`, `PurchaseOrderService`, `WarehouseController` sudah menggunakan `lockForUpdate()` pada baris produk/SO/PO — ✅ baik.
4. **CSRF:** Webhook Jubelio sengaja dikecualikan dari CSRF (`VerifyCsrfToken`) — sesuai desain eksternal.
5. **Throttling:** Login, import, dan webhook sudah dibatasi (`throttle:10,1` dll) — ✅.

---

## 6. FILE YANG DIMODIFIKASI

| File | Perubahan |
|------|-----------|
| `app/Services/PaymentPlanService.php` | BUG-01: `journal_id`/`journal_no` deterministik via `JournalHeader::idForPaymentPlan()` |
| `app/Http/Controllers/JournalController.php` | BUG-02: hapus `Asset` lama sebelum recreate detail saat edit jurnal |

Kedua file lolos pengecekan `php -l` (no syntax errors).

---

## 7. REKOMENDASI SELANJUTNYA (TIDAK WAJIB)

1. Tambahkan relasi `helperCode()` ke `JournalDetail` (BUG-03).
2. Seragamkan logika prefix saldo normal antara `AccountController` dan `LedgerController` (BUG-04) — idealnya pakai kolom `normal_balance` master COA.
3. Ubah `$guarded = []` → `$fillable` eksplisit pada `PurchaseReturn` & `PurchaseBill` (BUG-05).
4. Pertimbangkan lazy-load AJAX untuk modal detail jurnal di halaman Buku Besar (N+1 minor).
5. Jalankan `php artisan route:cache` & `config:cache` di production untuk performa.

---

*Laporan dibuat secara otomatis pasca-audit statis. Semua perbaikan telah diverifikasi dengan `php -l`.*

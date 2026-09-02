# Fix Summary: Tambah Harga Satuan ke Payment Plan

## Tanggal
2026-08-07

## Problem Statement
- Field "Nominal Aktual (Rp)" di form Edit Payment Plan tidak tersimpan karena JavaScript error (variabel `nominalMask`/`nominalAsli` tidak dideklarasikan)
- Tidak ada kolom "Harga Satuan" di Payment Plan, sehingga tidak mungkin menghitung nominal dari `harga_satuan × qty`

## Solusi yang Diterapkan

### 1. Fix JavaScript Error di `edit.blade.php`
**File:** `resources/views/payment_plan/edit.blade.php`

**Sebelum:**
```javascript
maskRupiah(nominalMask, nominalAsli); // Error: nominalMask tidak dideklarasikan
```

**Sesudah:**
```javascript
const nominalMask = document.getElementById('nominal_mask');
const nominalAsli = document.getElementById('nominal_asli');
if (nominalMask && nominalAsli) {
    maskRupiah(nominalMask, nominalAsli);
}
```

### 2. Tambah Kolom `harga_satuan` di Database
**File:** `database/migrations/2026_08_07_000001_add_harga_satuan_to_payment_plan.php`

- Menambahkan kolom `harga_satuan` (DECIMAL 15,2, nullable) setelah kolom `qty`
- Migration berhasil dijalankan via `php artisan migrate --path=database/migrations/2026_08_07_000001_add_harga_satuan_to_payment_plan.php`

### 3. Update Model PaymentPlan
**File:** `app/Models/PaymentPlan.php`

- Menambahkan `'harga_satuan'` ke dalam `$fillable` agar bisa di-mass assign

### 4. Update Form Create & Edit
**Files:**
- `resources/views/payment_plan/create.blade.php`
- `resources/views/payment_plan/edit.blade.php`

**Perubahan:**
- Menambahkan field "Harga Satuan (Rp)" dengan format Rupiah auto-mask
- Menambahkan JavaScript auto-calculation: `nominal = harga_satuan × qty`
- Field `harga_satuan` bersifat opsional; jika diisi, `nominal` otomatis terhitung

### 5. Update Controller Store & Update
**File:** `app/Http/Controllers/PaymentPlanController.php`

**Metode `store()` (line ~162):**
```php
$hargaSatuan = $request->harga_satuan;
$qty = $request->qty ?? 1;
$nominal = $request->nominal;
if ($hargaSatuan !== null && $hargaSatuan !== '') {
    $nominal = (float) $hargaSatuan * (float) $qty;
}
// ... kemudian simpan $nominal dan $hargaSatuan
```

**Metode `update()` (line ~313):**
```php
$hargaSatuan = $request->harga_satuan;
$qty = $request->qty ?? 1;
$nominal = $request->nominal;
if ($hargaSatuan !== null && $hargaSatuan !== '') {
    $nominal = (float) $hargaSatuan * (float) $qty;
}
// ... kemudian update $nominal dan $hargaSatuan
```

### 6. Update Index View
**File:** `resources/views/payment_plan/index.blade.php`

- Menambahkan kolom "Harga Satuan (Rp)" di tabel
- Memperbaiki `colspan` dari 19 menjadi 20 pada baris "Belum ada data"

## Logic Perhitungan

```
IF harga_satuan IS NOT NULL AND harga_satuan != ''
    nominal = harga_satuan × qty
ELSE
    nominal = nilai yang diinput manual di form
```

## Verifikasi yang Perlu Dilakukan

1. **Edit Payment Plan:**
   - Buka form Edit Payment Plan
   - Isi field "Harga Satuan (Rp)" dengan nilai tertentu (misal: 150000)
   - Ubah Qty menjadi 2
   - Pastikan field "Nominal (Rp)" otomatis ter-update menjadi 300.000
   - Klik "Update Perubahan Data"

2. **Index Payment Plan:**
   - Cek kolom "Harga Satuan (Rp)" menampilkan nilai yang baru diinput
   - Cek kolom "Pengajuan (Rp)" menampilkan hasil perhitungan (harga_satuan × qty)

3. **Create Payment Plan:**
   - Buat pengajuan baru dengan mengisi Harga Satuan dan Qty
   - Pastikan Nominal otomatis terhitung

4. **Journal Posting:**
   - Posting jurnal untuk data yang memiliki harga_satuan
   - Pastikan jurnal menggunakan `nominal_aktual_efektif` (aksesor yang sudah ada)

## Catatan
- Field `harga_satuan` opsional; jika tidak diisi, sistem tetap menggunakan `nominal` manual seperti sebelumnya
- Data lama yang tidak memiliki `harga_satuan` akan tetap menampilkan `-` di kolom Harga Satuan
- Perhitungan `harga_satuan × qty` hanya dilakukan jika `harga_satuan` diisi (tidak null dan tidak empty string)
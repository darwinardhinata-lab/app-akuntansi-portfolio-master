# INVENTORY_RULES.md — Aturan Inventory & Warehouse

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Modul: `App\Modules\Inventory\*`

---

## 1. RUANG LINGKUP

Modul Inventory menangani:
- Stok barang di gudang
- Mutasi barang (masuk/keluar)
- Gudang & lokasi penyimpanan
- Integrasi dengan Purchasing (GRN) dan Manufacturing (BOM, WO)

---

## 2. PRINSIP UTAMA

| Prinsip | Deskripsi |
|---|---|
| Satu sumber kebenaran stok | Stok di tabel inventory adalah sumber kebenaran |
| Mutasi mencatat setiap perubahan | Setiap naik/turun stok harus tercatat |
| Company scope | Setiap data inventory difilter `company_id` |
| Tidak menyentuh jurnal langsung | Inventory tidak menulis jurnal finansial secara langsung |

---

## 3. TABEL UTAMA (DIFOKUSKAN)

| Tabel | Deskripsi |
|---|---|
| `items` | Master barang/item |
| `warehouses` | Master gudang |
| `inventory_items` | Stok per barang per gudang |
| `inventory_movements` | Log mutasi stok |

> ⚠️ Catatan: Struktur tabel inventory mungkin belum sepenuhnya di-dokumentasikan di migration. Lakukan discovery di `database/migrations/` dan `app/Models/` sebelum mengubah.

---

## 4. ATURAN STOK

| Aturan | Detail |
|---|---|
| Stok tidak boleh negatif | Jika tersedia validasi, cegah stok minus |
| Mutasi mencatat actor | Siapa yang melakukan mutasi |
| Mutasi mencatat referensi | PO, GRN, WO, atau sumber lainnya |
| Integrasi dengan Purchasing | GRN menambah stok |
| Integrasi dengan Manufacturing | WO mengurangi stok komponen, menambah stok produk jadi |

---

## 5. DISCOVERY SEBELUM CODING

Sebelum mengubah modul Inventory:
1. Baca `app/Models/` untuk model inventory
2. Baca `database/migrations/` untuk struktur tabel
3. Baca `app/Modules/Inventory/` untuk service/logika
4. Baca `INVARIANTS.md` dan `docs/ARCHITECTURE.md`

_Last Updated: 2026-11-02_
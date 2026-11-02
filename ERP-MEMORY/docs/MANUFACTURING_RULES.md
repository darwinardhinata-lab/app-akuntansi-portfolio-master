# MANUFACTURING_RULES.md — Aturan Manufacturing (MFG)

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Modul: `App\Modules\Manufacturing\*`  
> Status: Native Laravel port, SUDAH SELESAI (per PROJECT_STATE.md)

---

## 1. RUANG LINGKUP

Modul Manufacturing menangani:
- Bill of Materials (BOM)
- MRP (Material Requirements Planning)
- Work Order (WO)
- Costing / biaya produksi

---

## 2. PRINSIP UTAMA

| Prinsip | Deskripsi |
|---|---|
| Native Laravel port | Modul ini sudah di-port ke Laravel, tidak perlu rewrite besar |
| Integrasi inventory | WO mengurangi stok komponen, menambah stok produk jadi |
| Integrasi biaya | Costing terkait dengan akun biaya produksi |
| Tidak menulis jurnal langsung | Semua posting via `PostingService` |

---

## 3. COMPONEN UTAMA

| Komponen | Deskripsi |
|---|---|
| BOM | Daftar komponen untuk membuat produk |
| MRP | Perencanaan kebutuhan material |
| Work Order | Permintaan produksi |
| Costing | Perhitungan biaya produksi |

---

## 4. ATURAN

| Aturan | Detail |
|---|---|
| BOM tidak boleh kosong | Setiap produk jadi butuh BOM |
| WO harus validasi stok komponen | Cukupkah stok untuk produksi? |
| Costing menggunakan BCMath | Semua biaya dihitung dengan BCMath scale 2 |
| Company scope | Setiap data manufacturing difilter `company_id` |

---

## 5. DISCOVERY SEBELUM CODING

Karena modul ini telah selesai, sebelum mengubah:
1. Baca `app/Modules/Manufacturing/`
2. Baca `database/migrations/` untuk tabel `mfg_*`
3. Baca `docs/ARCHITECTURE.md` untuk batasan modul
4. Baca `INVARIANTS.md` — jangan mengubah fondasi posting/ledger

_Last Updated: 2026-11-02_
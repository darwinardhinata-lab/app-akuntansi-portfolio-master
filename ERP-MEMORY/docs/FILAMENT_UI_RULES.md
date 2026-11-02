# FILAMENT_UI_RULES.md — Aturan UI Filament

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> Modul: `App\Filament\*`

---

## 1. RUANG LINGKUP

Modul Filament UI menangani:
- Panel admin (`/erp-panel/...`)
- Master data (divisi, dll)
- Halaman bank payments (`/erp-panel/bank-payments`)
- Cetak & preview dokumen

---

## 2. PRINSIP UTAMA

| Prinsip | Deskripsi |
|---|---|
| UI hanya, otorisasi di server | Hidden button bukan otorisasi, validasi ulang di server |
| Canonical page | `/erp-panel/bank-payments` adalah satu UI final untuk payment bank |
| Role-based access | Setiap endpoint divalidasi role/permission di server |
| Tidak percaya input client | Perhitungan finansial tidak pernah berdasarkan input client mentah |

---

## 3. HALAMAN UTAMA

| Halaman | Route | Deskripsi |
|---|---|---|
| Bank Payments | `/erp-panel/bank-payments` | Daftar & kelola permintaan pembayaran bank |
| Master Divisi | `/erp-panel/divisions` | Manajemen data divisi |
| Cetak Payment | `/erp-panel/bank-payments/{id}/print` | Cetak bukti pembayaran (multi-item + foto bukti) |

---

## 4. ATURAN UI

| Aturan | Detail |
|---|---|
| Hidden button bukan otorisasi | Server tetap memvalidasi setiap action |
| Role check di setiap endpoint | Setiap endpoint harus memeriksa role user |
| Upload privat | Attachment payment/reconciliation di private storage |
| SHA-256 server-side | Hash attachment dihitung server-side, bukan client |
| Download via authorized endpoint | Download butuh company scope check |

---

## 5. CETAK DOKUMEN

- Print multi-item + foto bukti (requirement Finance)
- Bukti attachment di private storage
- Download hanya via endpoint terotorisasi dengan company scope

_Last Updated: 2026-11-02_
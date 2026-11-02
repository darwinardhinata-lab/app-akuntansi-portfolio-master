# SALES_AR_RULES.md — Aturan Sales, AR dan Penerimaan Kas/Bank

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Modul: `App\Modules\Sales\*`, `App\Modules\AR\*`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 14)

---

## 1. RUANG LINGKUP

Modul Sales & AR menangani:
- Penjualan lokal dan ekspor
- Surat jalan dan dispatch FG
- Rekap pengiriman
- Customer invoice dan debit note
- Penerimaan kas/bank
- AR aging

Outbound Payment Plan v33 **tidak dipaksakan** menjadi flow penerimaan uang.

---

## 2. ALUR DAN KONTROL

### 2.1 Order → Delivery
| Kontrol | Detail |
|---------|--------|
| Link WO/customer order | Setiap delivery terhubung ke WO atau customer order |
| Qty FG available | Tidak boleh melebihi stok FG yang tersedia |
| Partial/full delivery | Dukung parsial dan penuh |
| Dokumen pengiriman | Nomor polisi, consignee, seal, AWB dan dokumen sesuai kebutuhan |

### 2.2 Delivery → Invoice
| Kontrol | Detail |
|---------|--------|
| Invoice lokal/ekspor | FOB/CMT jika relevan |
| Currency/rate | Nilai mata uang transaksi dan base currency |
| NW/GW, carton, HS, dokumen | Lengkap sesuai kebutuhan |
| Qty invoice | Tidak boleh melebihi basis yang diizinkan |

### 2.3 Invoice → AR
| Kontrol | Detail |
|---------|--------|
| Open-item per invoice | Setiap invoice melacak outstanding |
| Due date | Jatuh tempo dicatat |
| Credit note/retur | Tertaut ke invoice asli |
| Akun pendapatan/AR | Sesuai mapping COA |

### 2.4 Receipt → Allocation
| Kontrol | Detail |
|---------|--------|
| Rekening masuk dan bukti bank | Setiap receipt terhubung ke rekening dan bukti |
| Satu receipt ke beberapa invoice | Dukung alokasi multi-invoice |
| Sisa unapplied | Tidak terpakai tetap tercatat |
| ID transfer unik | Mencegah import ganda |

### 2.5 Kurs / GL
| Kontrol | Detail |
|---------|--------|
| Nilai mata uang transaksi dan base currency | Dilacak terpisah |
| Realized FX pada pelunasan | Dicatat saat pelunasan |
| Revaluasi terpisah | Tidak dicampur dengan realized |
| Nama akun USD | Tidak otomatis membuktikan nilai native |

---

## 3. ATURAN UMUM

| Aturan | Detail |
|--------|--------|
| Company scope | Setiap data sales/AR difilter `company_id` |
| Tidak ada penjualan otomatis | Hanya saat transfer milik sendiri dengan approval |
| Financial posting sekali per event | Hindari double posting |
| Reuse party dan stock services | Tidak buat service paralel |
| Posting via PostingService | Jangan menulis jurnal langsung |

---

## 4. DISCOVERY SEBELUM CODING

Sebelum mengubah modul Sales/AR:
1. Baca `app/Models/` untuk model sales dan AR
2. Baca `database/migrations/` untuk struktur tabel
3. Baca `app/Modules/Sales/` dan `app/Modules/AR/` untuk service/logika
4. Baca `INVARIANTS.md` dan `docs/ARCHITECTURE.md`

---

## 5. FILE TERKAIT

- `docs/PURCHASING_AP_RULES.md` — Aturan purchasing dan AP (seberang lawan dari sales)
- `docs/INVENTORY_RULES.md` — Aturan inventory (FG dispatch mengurangi stok)
- `docs/ACCOUNTING_RULES.md` — Aturan posting dan COA
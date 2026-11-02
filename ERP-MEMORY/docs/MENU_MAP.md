# MENU_MAP.md — Pemetaan 151 Menu Addonan ke Target ERP

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (Lampiran A)

---

## 1. RINGKASAN STATISTIK

| Metrik | Nilai |
|--------|-------|
| Total entri menu sumber | 151 |
| URL berbeda | 114 |
| Rekaman view | 207 |
| Tujuan kembali ke dasbor | 17 |
| Menu verified | 0 |
| Menu unmapped | 133 |
| Reuse candidate | 9 |
| Mapped-not-implemented | 6 |
| Heading | 3 |

---

## 2. ATURAN PEMETAAN

| Aturan | Detail |
|--------|--------|
| Setiap entri punya disposition dan source reference | Menu aktif menuju halaman benar |
| Direct URL/ID company lain ditolak | |
| Unresolved mapping memblokir posting | |
| User/COA lama tetap utuh | |
| Tidak ada duplikasi logika | Alias tanpa duplikasi |

---

## 3. FILE TERKAIT

- `docs/MASTER_DATA_CROSSWALK.md` — Crosswalk master data
- `docs/FILAMENT_LIVEWIRE_ADOPTION.md` — Panduan adopsi UI Filament
- `docs/ARCHITECTURE.md` — Arsitektur sistem

---

## 4. PEMETAAN MASTER DATA

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 1 | Dashboard | Dashboard canonical | Unmapped |
| 2 | Master Perusahaan | Master company canonical | Unmapped |
| 3 | Master COA | Master COA canonical | Unmapped |
| 4 | Master Supplier | Party master canonical (role supplier) | Unmapped |
| 5 | Master Customer | Party master canonical (role customer) | Unmapped |
| 6 | Master Material | Item master canonical | Unmapped |
| 7 | Master Varian | Item variant master canonical | Unmapped |
| 8 | Master Gudang | Warehouse master canonical | Unmapped |
| 9 | Master Rak | Location rack master canonical | Unmapped |
| 10 | Master Bank | Bank account master canonical | Unmapped |
| 11 | Master Kurs | Currency rate master canonical | Unmapped |
| 12 | Master User | User master canonical | Unmapped |
| 13 | Master Role | Role master canonical | Unmapped |
| 14 | Master Divisi | Division master canonical | Unmapped |
| 15 | Master Satuan | Unit of measure master canonical | Unmapped |

---

## 5. PEMETAAN PURCHASING

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 16 | Purchase Request | PR canonical | Unmapped |
| 17 | Purchase Order | PO canonical | Unmapped |
| 18 | PO dari IPR | PO from IPR canonical | Unmapped |
| 19 | Add/Reduce PO | PO amendment canonical | Unmapped |
| 20 | MOQ Tracking | MOQ tracking canonical | Unmapped |
| 21 | GRN / Terima Barang | GRN canonical | Unmapped |
| 22 | QC | Quality control canonical | Unmapped |
| 23 | Purchase Bill | Purchase bill canonical | Unmapped |
| 24 | Three-Way Match | 3-way match canonical | Unmapped |
| 25 | AP Open Item | AP outstanding canonical | Unmapped |
| 26 | AP Aging | AP aging canonical | Unmapped |
| 27 | DP Allocation | DP allocation canonical | Unmapped |
| 28 | Progress Payment | Progress payment canonical | Unmapped |
| 29 | Final Payment | Final payment canonical | Unmapped |
| 30 | Retention | Retention canonical | Unmapped |
| 31 | Purchase Return | Return canonical | Unmapped |
| 32 | Rekap PO | PO recap canonical | Unmapped |
| 33 | Status Material-PO | Material-PO status canonical | Unmapped |
| 34 | BOM/MRP Link | BOM-MRP link canonical | Unmapped |

---

## 6. PEMETAAN PAYMENT PLAN

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 41 | Overview | Dashboard, rekap klik ke detail | Unmapped |
| 42 | Requests | Register pengajuan, draft, status | Unmapped |
| 43 | Approvals | Inbox aksi sesuai user dan state | Unmapped |
| 44 | Balances | Rekening, saldo acuan, penerimaan aktual | Unmapped |
| 45 | Petty | Pengajuan kas kecil | Unmapped |
| 46 | Reports | Frozen laporan kas tujuh hari | Unmapped |
| 47 | Forms | Laporan Permohonan, Settlement, List of Payments | Unmapped |
| 48 | Owner Cash | Historis kas owner immutable | Unmapped |
| 49 | Journal History | Jurnal sumber historis | Unmapped |
| 50 | Document Translator | Penerjemah terpisah, review manusia | Unmapped |
| 51 | Settings | Anggota/role/divisi, saldo awal, konfigurasi | Unmapped |

---

## 7. PEMETAAN INVENTORY

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 61 | Stock Card | Kartu stok canonical | Unmapped |
| 62 | Stock Opname | Stock opname canonical | Unmapped |
| 63 | Inventory Transfer | Transfer canonical | Unmapped |
| 64 | Stock Adjustment | Adjustment canonical | Unmapped |
| 65 | Deadstock | Deadstock classification canonical | Unmapped |
| 66 | Stock Lot | Lot tracking canonical | Unmapped |
| 67 | Opening Stock | Opening stock wizard canonical | Unmapped |
| 68 | MR/Issue | Material requisition canonical | Unmapped |
| 69 | BBK/Receipt | Goods receipt canonical | Unmapped |
| 70 | Reservation | Stock reservation canonical | Unmapped |

---

## 8. PEMETAAN MANUFACTURING

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 86 | BOM | BOM canonical | Unmapped |
| 87 | MRP | MRP canonical | Unmapped |
| 88 | Work Order | WO canonical | Unmapped |
| 89 | Routing | Routing canonical | Unmapped |
| 90 | Process Template | Process template canonical | Unmapped |
| 91 | Production Issue | Production issue canonical | Unmapped |
| 92 | Production Output | Production output canonical | Unmapped |
| 93 | Rework | Rework canonical | Unmapped |
| 94 | Scrap | Scrap canonical | Unmapped |
| 95 | WO Close | WO close canonical | Unmapped |
| 96 | Costing | Costing canonical | Unmapped |

---

## 9. PEMETAAN SALES / AR

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 106 | Sales Order | Sales order canonical | Unmapped |
| 107 | Delivery / Surat Jalan | Delivery canonical | Unmapped |
| 108 | FG Dispatch | FG dispatch canonical | Unmapped |
| 109 | Customer Invoice | Customer invoice canonical | Unmapped |
| 110 | Debit/Credit Note | Debit/credit note canonical | Unmapped |
| 111 | Receipt | Receipt canonical | Unmapped |
| 112 | Receipt Allocation | Receipt allocation canonical | Unmapped |
| 113 | AR Aging | AR aging canonical | Unmapped |
| 114 | Subcontract | Subcontract canonical | Unmapped |
| 115 | Mechanic | Mechanic canonical | Unmapped |
| 116 | Scrap Disposal | Scrap disposal canonical | Unmapped |

---

## 10. PEMETAAN EXIM / CUSTOMS

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 134 | BC 2.3 | Form BC canonical | Unmapped |
| 135 | BC 2.6.2 | Form BC canonical | Unmapped |
| 136 | BC 2.7 | Form BC canonical | Unmapped |
| 137 | BC 4.0 | Form BC canonical | Unmapped |
| 138 | BC 3.0 | Form BC canonical | Unmapped |
| 139 | BC 2.5 | Form BC canonical | Unmapped |
| 140 | BC 2.6.1 | Form BC canonical | Unmapped |
| 141 | BC 4.1 | Form BC canonical | Unmapped |
| 142 | Laporan Pemasukan Barang | Laporan customs canonical | Unmapped |
| 143 | Laporan Pengeluaran Barang | Laporan customs canonical | Unmapped |
| 144 | Laporan Mutasi Barang Jadi | Laporan customs canonical | Unmapped |

---

## 11. PEMETAAN MIS / REPORTS

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 145 | Laporan Progres Produksi | Laporan canonical | Unmapped |
| 146 | Transaksi Inventaris | Laporan canonical | Unmapped |
| 147 | Rekap Pembelian | Laporan canonical | Unmapped |
| 148 | Buku Besar | Alias GL canonical | Unmapped |
| 149 | Rekonsiliasi Periode | Laporan canonical | Unmapped |
| 150 | Laporan Bea Cukai | Laporan customs canonical | Unmapped |

---

## 12. PEMETAAN APPROVAL / ADMIN

| No | Menu Sumber | Target / Tindakan | Status |
|----|-------------|-------------------|--------|
| 161 | Approve | Inbox task lintas modul | Unmapped |
| 162 | Dok Rekonsiliasi CEISA | CEISA ↔ stock document reconciliation | Unmapped |
| 163 | Daftar Karyawan | Identity ERP + membership/role | Unmapped |
| 164 | Log Aplikasi | Audit log append-only | Unmapped |
| 165 | Opsi | Implementasi nyata / nonaktif jelas | Unmapped |
| 166 | Aplikasi Addon Diagram Alir | Flow dokumentasi berbasis registry route | Unmapped |
| 167 | Options | Settings nyata; gabungkan opsi duplikat | Unmapped |
# MENU_MAP.md — Pemetaan 151 Menu Addonan ke Target ERP

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (Lampiran A)

---

## RINGKASAN PEMETAAN

| Kategori | Jumlah Menu Sumber | Target ERP | Catatan |
|----------|-------------------|------------|---------|
| Dashboard | 1 | Dashboard Filament (`/erp-panel`) | Status belum tersedia jika sumber tidak terhubung |
| Master Data | ~20 | Master divisi, unit, party, material, COA, bank | Satu master nonfinansial dulu |
| Purchasing | ~15 | IPR, PO, GRN, invoice, AP | Three-way match required |
| Inventory | ~20 | Stok, mutasi, gudang, rak, lot, opname | Satu mutation ledger |
| Manufacturing | ~15 | BOM, MRP, WO, routing, costing | Routing configurable |
| Sales & AR | ~15 | Order, delivery, invoice, receipt, AR aging | Reuse party dan stock services |
| Payment Plan | 11 | Payment Plan v33 (Blade UI + API) | Pertahankan flow vanilla JS v33 |
| EXIM / Customs | ~15 | BC forms, laporan pabean | Document type+direction canonical |
| Reports / MIS | ~20 | Laporan canonical + drill-down | Tidak duplikasi logika |
| Approval / Admin | ~10 | Inbox task, user, log aplikasi, opsi | Otorisasi server |
| Import / Export | ~10 | Staging, preview, validate, commit | Tidak import CRUD bawaan |

Total: 151 entri sidebar (114 URL berbeda, 207 rekaman view, 17 tujuan kembali ke dasbor)

---

## DISPOSITION SETIAP ENTRI

Setiap entri menu harus memiliki salah satu disposition berikut:

| Disposition | Arti | Aksi |
|-------------|------|------|
| `IMPLEMENTED` | Sudah ada di ERP Marvel-Dard | Verifikasi route dan otorisasi |
| `MAPPED_NOT_IMPLEMENTED` | Terpeta ke target tapi belum dibuat | Buat sesuai roadmap B06–B10 |
| `REUSE_CANDIDATE` | Bisa digunakan ulang | Evaluasi sebelum implementasi baru |
| `HEADING` | Hanya judul grup | Tidak perlu aksi |
| `UNMAPPED` | Belum memiliki target | Perlu discovery atau ditandai sebagai no-go |
| `PLACEHOLDER_INACTIVE` | Placeholder, nonaktif | Beri alasan, jangan hapus tanpa review |

---

## STATUS VERIFIKASI (dari audit)

- **133 unmapped** — Belum memiliki target ERP yang dikonfirmasi
- **9 reuse candidate** — Bisa digunakan ulang setelah evaluasi
- **6 mapped-not-implemented** - Terpeta tapi belum dibangun
- **3 heading** — Judul grup, tidak perlu aksi
- **Tidak ada verified** — Belum ada menu yang terverifikasi 100% siap produksi

---

## ATURAN PEMETAAN MENU

1. Setiap entri punya disposition dan source reference
2. Menu aktif menuju halaman yang benar
3. Direct URL / ID company lain ditolak (company scope)
4. Unresolved mapping memblokir posting
5. User / COA lama tetap utuh, tidak di-overwrite
6. Tambahkan `presentation_driver`, `route`, `implementation_status`, `policy` dan `evidence` pada menu-map
7. Jangan menimpa bukti yang lebih baru
8. Tiga bahasa ID/EN/ZH, label/navigation sesuai MenuRegistry yang ada, bukan menu duplikat

---

## FILE TERKAIT

- `docs/MASTER_DATA_CROSSWALK.md` — Crosswalk master data lintas sistem
- `docs/FILAMENT_LIVEWIRE_ADOPTION.md` — Panel Filament dan resource mapping
- `docs/API_CONTRACTS.md` — Endpoint API yang terkait dengan menu
# EXIM_CUSTOMS_RULES.md — Aturan EXIM, BC dan Dokumen Pabean

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Modul: `App\Modules\EXIM\*`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 10, 16-18)

---

## 1. RUANG LINGKUP

Modul EXIM & Customs menangani:
- Dokumen BC (Bea Cukai) berbagai jenis
- Laporan pabean (pemasukan, pengeluaran, WIP, mutasi)
- Impor data (CEISA 4.0 dan lainnya)
- Rekonsiliasi dokumen pabean

---

## 2. DOKUMEN BC (DARI AUDIT LAMPIRAN A)

| No | Menu Sumber | Target / Tindakan |
|----|-------------|-------------------|
| 106 | BC 2.3 | Form BC canonical; type + arah eksplisit dan trace |
| 107 | BC 2.6.2 | Form BC canonical; type + arah eksplisit dan trace |
| 108 | BC 2.7 | Form BC canonical; type + arah eksplisit dan trace |
| 109 | BC 4.0 | Form BC canonical; type + arah eksplisit dan trace |
| 110 | BC 3.0 | Form BC canonical; type + arah eksplisit dan trace |
| 111 | BC 2.5 | Form BC canonical; type + arah eksplisit dan trace |
| 112 | BC 2.6.1 | Form BC canonical; type + arah eksplisit dan trace |
| 113 | BC 2.7 | Form BC canonical; type + arah eksplisit dan trace |
| 114 | BC 4.1 | Form BC canonical; type + arah eksplisit dan trace |

---

## 3. LAPORAN PABEAN

| No | Menu Sumber | Target / Tindakan |
|----|-------------|-------------------|
| 115 | Laporan Pemasukan Barang | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 116 | Laporan Pengeluaran Barang | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 117 | Laporan Posisi Barang Dalam Proses | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 118 | Laporan Mutasi Bahan Baku dan Bahan Penolong | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 119 | Laporan Mutasi Barang Jadi | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 120 | Laporan Mutasi Reject dan Sisa Proses Produksi (Scrap/Waste) | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 121 | Laporan Mutasi Barang Modal dan Barang Lain | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 138 | Lap Pemasukan Barang Per Dokumen Pabean | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 139 | Lap Pengeluaran Barang Per Dokumen Pabean | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 140 | Lap Posisi Barang Dalam Proses (WIP) | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 141 | Lap Mutasi Bahan Baku dan Bahan Penolong | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 142 | Lap Mutasi Barang Jadi | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 143 | Lap Mutasi Barang Sisa dan Sampah | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |
| 144 | Lap Mutasi Mesin | Laporan customs canonical; filter + export + trace; alias tanpa duplikasi logika |

---

## 4. IMPOR DATA

| No | Menu Sumber | Target / Tindakan |
|----|-------------|-------------------|
| 122 | Impor Data & Unggah | Header grup import, bukan link rusak; import center canonical |
| 123 | Impor Data CEISA 4.0 & Lainnya | CEISA import staging; preview/validate/commit/reconcile |

---

## 5. ATURAN PENTING

| Aturan | Detail |
|--------|--------|
| Document type + arah eksplisit | Setiap form BC harus memiliki type dan arah (import/export) |
| Trace ke stock movement | Dokumen BC harus dapat ditrace ke stock movement |
| Jangan klaim integrasi H2H | Hanya berdasarkan menu import tidak cukup |
| Document type canonical | Bangun document type + direction canonical |
| Staging immutable | Impor menggunakan staging immutable, source hash, raw row |
| Schema version | Setiap impor menyimpan schema version |
| Preview/validate/commit | Alur impor: preview → validate → commit |
| Idempotency | Impor harus idempotent |
| Reconciliation | Impor harus direkonsiliasi |
| AGPO gate | AGPO tetap config gate sampai definisi proses disahkan |
| CEISA ↔ stock doc reconciliation | Dokumen rekonsiliasi CEISA 4.0 |

---

## 6. DISCOVERY SEBELUM CODING

Sebelum mengubah modul EXIM:
1. Baca `app/Models/` untuk model EXIM/customs
2. Baca `database/migrations/` untuk tabel `exim_*` atau `customs_*`
3. Baca `app/Modules/EXIM/` untuk service/logika
4. Baca `INVARIANTS.md` dan `docs/ARCHITECTURE.md`
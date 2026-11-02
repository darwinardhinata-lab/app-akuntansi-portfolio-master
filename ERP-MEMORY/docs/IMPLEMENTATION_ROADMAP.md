# IMPLEMENTATION_ROADMAP.md — Roadmap Implementasi B01–B12

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: Audit penggabungan ERP Marvel-Dard / Addonan / Payment Plan v33 (Lampiran B) dan Prompt Cline Filament/Livewire 27 Okt 2026

---

## RINGKASAN TAHAPAN B01–B12

| Fase | ID | Tahapan | Tujuan Utama | Status |
|------|-----|---------|--------------|--------|
| Foundation | B01 | S0 - Discovery dan baseline matriks | Buktikan baseline sebelum modifikasi | ✅ SELESAI |
| Foundation | B02 | S1 - Foundation, master dan menu registry | Satu sumber master/permission; crosswalk dan menu registry | 🔄 IN_PROGRESS |
| Foundation | B03 | S2 - Posting adapter dan matriks | Semua modul memakai mesin jurnal ERP yang sama | 🔄 IN_PROGRESS |
| Foundation | B04 | S3 - Payment Plan parity dan adapter | Integrasi domain v33 secara setara | 🔄 IN_PROGRESS |
| Foundation | B05 | S4 - Saldo, allocation dan posting payment | Tutup gap finansial v33 tanpa mengubah approval flow | 🔄 IN_PROGRESS |
| Operations | B06 | S5A - IPR, PO, GRN, invoice dan AP | Adopsi PO umum/IPR, tracking, MOQ, receipt dan retur | ⏳ TODO |
| Operations | B07 | S5B - Inventory ledger dan opname | Satu mutation ledger untuk mutasi stok | ⏳ TODO |
| Operations | B08 | S6 - BOM, MRP, WO, routing dan costing | Adopsi CBD/BOM, MRP, SPK, routing configurable | ⏳ TODO |
| Operations | B09 | S7 - Sales, AR, subkontrak, mekanik, scrap | Sales lokal/ekspor, delivery, invoice, receipt allocation, AR aging | ⏳ TODO |
| Operations | B10 | S8 - EXIM, reports dan import/export | Realisasikan seluruh jenis BC dan tujuh laporan | ⏳ TODO |
| Release | B11 | S9A - UAT, migrasi dan restore rehearsal | Migration runbook reproduktif; dry run staging | ⏳ TODO |
| Release | B12 | S9B - Paket cutover yang reviewable | Paket final aktivasi; rollback decision tree | ⏳ TODO |

Setiap tahapan berakhir dengan ringkasan: file yang berubah, migration, command test + hasil aktual, risiko tersisa, keputusan konfigurasi belum disahkan, langkah lanjut. Jangan menyatakan test lulus jika tidak dijalankan.

---

## PRIORITAS P0 (SEBELUM TRANSAKSI NYATA)

Sebelum transaksi nyata diizinkan, semua hal berikut harus selesai:

1. Inventaris repository Marvel-Dard dan kunci kontrak jurnal matriks melalui regression test
2. Satu identitas perusahaan, user, COA, supplier/customer, material dan bank; tidak mengimpor master tanpa crosswalk
3. Port adapter identitas dan penyimpanan v33; jangan percaya header Sites pada server umum
4. Cegah jurnal ganda, pembayaran ganda, perubahan transaksi posted dan kebocoran lintas perusahaan
5. Sahkan mandat rekening: source v33 memakai IDR 3 dan USD 1; rencana lama berbeda

---

## PRIORITAS P1 DAN P2

| Prioritas | Ruang Lingkup |
|-----------|---------------|
| P1 | purchasing sampai GRN, invoice/AP, Payment Plan, inventory ledger, rekonsiliasi dan laporan |
| P2 | BOM/MRP, WO/routing produksi, sales/AR, subkontrak, mekanik, scrap, serta dokumen customs |

Semua prioritas P1 dan P2 masuk backlog; aktivasi mengikuti dependensi dan hasil UAT, bukan sekadar menu selesai.

---

## GATE AKTIVASI (B11–B12)

Go ke production hanya jika seluruh syarat berikut terpenuhi:

| No | Syarat | Bukti yang Diperlukan |
|----|--------|----------------------|
| 1 | Seluruh P0 ditutup | Dokumen penutupan P0 |
| 2 | UAT kritis lulus | Tanda tangan UAT operasional, UX dan keamanan |
| 3 | Restore DB+blob terbukti | Hasil dry run restore staging |
| 4 | Saldo dan quantities direkonsiliasi | Laporan rekonsiliasi AP/AR/stock/cash/GL |
| 5 | Rekening sumber disahkan | Keputusan mandat rekening (IDR 3 / USD 1) |
| 6 | Personel role sudah ditugaskan | Daftar PIC dan role operasional |
| 7 | Rollback decision tree tersedia | `docs/CUTOVER_ROLLBACK.md` diisi lengkap |

**Catatan:** Setelah ada transaksi baru, rollback tidak boleh restore lama tanpa replay/reconciliation delta.

---

## ROLLBACK CRITERIA

Stop dan rollback jika:
- Ada selisih tak terjelaskan antara count/nilai/qty sebelum dan sesudah migrasi
- AP/AR/stock/GL tidak direkonsiliasi
- Attachment hashes tidak cocok
- Role nyata UAT belum disahkan
- Restore DB/blob gagal
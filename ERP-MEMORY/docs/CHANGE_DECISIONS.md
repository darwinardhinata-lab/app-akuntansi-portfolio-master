# CHANGE_DECISIONS.md — Log Keputusan Arsitektur

> Last Updated: 2026-10-30  
> Branch: `feat/marvel-dard-integration`  
> Format: Tanggal | Keputusan | Alasan | Referensi (issue/PR/commit)

---

## FORMAT ENTRI

| Tanggal | Keputusan | Alasan | Referensi |
|---------|-----------|--------|-----------|
| YYYY-MM-DD | Ringkasan keputusan | Mengapa keputusan ini diambil | Issue/PR/commit ID |

---

## DAFTAR KEPUTUSAN

| Tanggal | Keputusan | Alasan | Referensi |
|---------|-----------|--------|-----------|
| 2026-10-29 | R1 scope terbatas pada bank IDR saja | USD, petty cash, DP, retention ditunda ke fase berikutnya | PROJ-001 / R1 planning |
| 2026-10-29 | Canonical page = `/erp-panel/bank-payments` | Satu UI final untuk semua payment bank, menghindari duplikasi panel | UX-002 |
| 2026-10-29 | Attachment reconciliation context = bukan hanya request | Butuh bukti kontekstual penuh, tidak hanya request attachment | FIN-005 |
| 2026-10-29 | Print multi-item + foto bukti | Requirement dari tim Finance untuk audit fisik | FIN-006 |
| 2026-10-30 | F04 = AP residual completion | Langkah alami setelah R1 bank payment selesai | AP-004 |
| 2026-10-30 | Credit notes via reconciliation, tidak ada tabel `credit_notes` | Hindari tabel tambahan, gunakan existing reconciliation flow | AP-004b |
| 2026-10-30 | Tambah 3-way match ke `purchase_bills` dan `purchase_invoices` | Mendukung validasi PO ↔ GRN ↔ Bill sebelum posting | AP-003 |
| 2026-10-30 | Buat tabel `ppl_ap_payment_allocations` dengan idempotency key | Cegah double allocation di payment plan | PPL-001 |
| 2026-10-30 | Buat struktur `ERP-MEMORY/` sebagai source-of-truth untuk AI | Kompensasi context window LLM, dokumentasi version-controlled | MEM-001 |

---

## CARA MENAMBAH ENTRI BARU

Sebelum membuat keputusan arsitektur baru:

1. Buka `docs/CHANGE_DECISIONS.md`
2. Tambahkan baris di bagian atas tabel (terbaru di atas)
3. Isi: Tanggal | Keputusan | Alasan | Referensi
4. Commit dengan pesan: `docs: record decision – <ringkasan>`

---

_Last Updated: 2026-10-30_
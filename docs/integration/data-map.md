# Data map discovery B01

Root absolut: `D:\xampp\htdocs\app-akuntansi-portfolio-master`. Semua nama tabel di sini ditemukan pada source/migration, bukan verifikasi isi DB produksi.

| Sumber / domain | Target yang sudah ada | Batas / aksi berikutnya |
|---|---|---|
| Company | companies / Company / OperationalCompany | Single MGI guard; jangan merge label MGC/MGI otomatis |
| Identity | User dan company context/membership | Parity role v33 belum dipetakan |
| Party | parties / Party; helper_codes legacy | Jangan merge berdasar nama; helper fixture bukan party migration |
| COA | accounts.account_code | Relasi ledger code-based; unresolved crosswalk harus digate pada B02 |
| Legacy mapping | legacy_map / LegacyMap | Ada status/quarantine; audit company/unique semantics sebelum menambah schema |
| Manual/operational GL | journal_headers + journal_details | Shared ledger; writer tersebar; journal_id sebagai link |
| PO/GRN/AP | purchase_orders, purchase_receipts, purchase_bills | GRN compatibility path; belum invoice matching penuh |
| Stock | inventory_ledgers dan manufacturing MaterialLedger | Bukan satu unified mutation ledger saat ini |
| Payment ERP lama | transaksi_payment_plan | Bukan tabel pp_* v33; tidak import/port otomatis |
| V33 attachments/history | Belum dipetakan | Perlu source schema/domain discovery B04 dan backup data berizin B11 |

Coverage import aktual: tidak ada import/master/transaksi yang dijalankan. Tidak ada approved crosswalk baru.
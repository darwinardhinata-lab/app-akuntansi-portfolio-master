# O04 — Rekonsiliasi kartu stok EXPENSE dan GL

Scope: fixture canonical single-MGI, bahan penolong PCS, penerimaan tanpa PPN, lalu issue EXPENSE dan void issue melalui route normal. Koneksi MariaDB observer berbeda dari writer membaca kondisi committed. Bukti ini tidak mencakup mixed-cost, pembulatan, WIP, transfer atau opname.

| Transaksi | Kartu stok | Qty / nilai berjalan | GL |
| --- | --- | --- | --- |
| Receipt | IN 5 PCS / 500.00 | 5 PCS / 500.00 | Dr 114004 / Cr 211001, 500.00 |
| Issue EXPENSE | OUT 2 PCS / 200.00 | 3 PCS / 300.00 | Dr 510010 / Cr 114004, 200.00 |
| Void issue | IN 2 PCS / 200.00 | 5 PCS / 500.00 | Dr 114004 / Cr 510010, 200.00 |

Saldo master dan nilai kartu akhir cocok dengan saldo net Dr 114004 sebesar 500.00. Tidak ada mutasi duplikat di `inventory_ledgers`. Void kedua ditolak dan tidak menambah mutasi atau jurnal. Tidak ditemukan selisih pada fixture ini; tidak ada perubahan runtime.

Bukti disposable MariaDB: 1 tes / 31 assertion, PHPUnit dan runner exit 0, proses dan datadir dibersihkan. Artefak lokal: `D:/ERP-Recovery/finance-uat-local-895d410/o04-conservation-final/auxiliary-issue-conservation.json`, `maria.log` dan `summary.json`.
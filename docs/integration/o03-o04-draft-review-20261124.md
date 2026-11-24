# Review draft O03/O04 — 24 November 2026

Tip integrasi `feat/marvel-dard-integration` dan merge base kedua draft saat fetch: `a8063a0dca462299017125ef8a1574d0ed2e69c1`. Tidak ada dependensi commit antardraft; gate bisnis O04 tetap mengikuti master plan. `git merge-tree --write-tree` atas kedua head di bawah mendeteksi konflik konten pada `tests/Integration/MaterialProcurementMysqlTest.php`. Jika keduanya disetujui, pertahankan skenario keduanya saat rekonsiliasi, uji kasus terkait delta, dan minta approval Darwin untuk head hasil rekonsiliasi sebelum merge.

| Draft | Head sebelum follow-up | Runtime | Bukti target MariaDB yang digunakan kembali |
| --- | --- | --- | --- |
| [#21](https://github.com/darwinardhinata-lab/app-akuntansi-portfolio-master/pull/21) | `dd8140b5228fd093f0101fc509c7ee2fd698f123` | Tidak berubah; tes receipt parsial dan void receipt parsial terbaru. | 2 tes / 71 assertion, exit 0; rekonsiliasi sumber, stok dan GL. |
| [#22](https://github.com/darwinardhinata-lab/app-akuntansi-portfolio-master/pull/22) | `76d5b49a322b4ce57fd9be18f027317c89ebd887` | Redirect memakai `work_order_id` pada issue yang sudah tersimpan; EXPENSE tanpa field memberi sukses, WIP tanpa SPK tetap ditolak. Tidak ada perubahan posting atau skema. | 3 tes / 44 assertion, exit 0. Dua issue 3 PCS terhadap stok 5 PCS: loser menunggu lock material lalu ditolak tanpa write. Hasil: satu issue, satu OUT 3 PCS / 300.00, sisa 2 PCS, satu jurnal seimbang Dr `510010` / Cr `114004`, 300.00. |

## Follow-up #22: dua POST EXPENSE identik, stok cukup

Form SPK mengirim CSRF, SPK, line, tanggal, material, jenis dan qty; CSRF bukan identitas transaksi, SPK/line dapat dipakai untuk banyak issue, dan `issue_number` unik dibuat server **setelah** request masuk. Controller tidak menerima referensi transaksi atau idempotency key. Karena tidak ada identitas yang bisa digunakan ulang, tes mengirim payload form normal yang identik dua kali oleh operator yang sama dengan stok 5 PCS dan masing-masing 2 PCS. Ini mengukur perilaku aktual, bukan bukti retry dengan identitas yang sama, dan bukan uji browser.

MariaDB disposable: **1 tes / 32 assertion**; PHPUnit dan runner exit `0`, instance dan datadir dibersihkan. Observer terpisah melihat state committed:

| POST | Respons | Issue / OUT kumulatif | Saldo stok | Jurnal issue baru |
| --- | --- | --- | --- | --- |
| 1 | 302 ke SPK, flash sukses | `AMI-20261124-0001`; 1 issue, 1 OUT | 3 PCS / 300.00 | `GJ-20261124-0001` |
| 2 | 302 ke SPK yang sama, flash sukses | `AMI-20261124-0002`; 2 issue, 2 OUT | 1 PCS / 100.00 | `GJ-20261124-0002` |

Tiap OUT 2 PCS / 200.00; tiap jurnal Dr `510010` / Cr `114004` sebesar 200.00. Bukti lokal: `D:/ERP-Recovery/finance-uat-local-895d410/o04-repeat-verified/auxiliary-issue-repeat.json`, `maria.log`, dan `summary.json`. Bukti 3/44 tidak dijalankan ulang karena delta tidak mengubah runtime atau tes yang sudah ada.

Follow-up hanya berisi tes dan dokumentasi. Jangan deduplikasi berdasarkan isi payload. Keputusan yang masih diperlukan: apakah submit ulang harus berefek satu kali; jika ya, definisikan identitas per intent (dikirim ulang untuk retry, baru untuk transaksi sah berikutnya), scope/masa berlaku, respons untuk replay dan konflik payload dengan key sama, serta atomisitas terhadap issue, ledger dan GL.

## Kriteria belum ditutup

- **O03:** receipt/void yang diuji tidak menerima milestone. Bukti toleransi partial/over-receipt, QC/quarantine, three-way match, serta retur dan rekonsiliasi AP/stok/GL untuk alur tersisa masih diperlukan.
- **O04:** bukti ledger, konservasi qty dan race terbatas pada issue bahan penolong yang diuji. Kontrak replay terbuka; cakupan ledger/konservasi lintas alur, transfer dua kaki/in-transit, dan opname dengan approval belum ditutup.
- **Pilot:** tidak ada persetujuan staging/PIC/restore independen yang tercatat pada checkpoint dan review yang diperiksa. Tidak ada akses staging atau penutupan AC07/AC10/AC09 target. UAT Finance `DEFERRED`; produksi `NO-GO`.
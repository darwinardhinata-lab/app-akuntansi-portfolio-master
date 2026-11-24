# O04 — Valuasi mixed-cost bahan penolong

Fixture MGI canonical; dua receipt tanpa PPN, issue EXPENSE parsial 2 PCS, lalu void issue melalui rute normal. Observer MariaDB terpisah membaca committed state. Bukti biaya tunggal tetap berlaku (1 tes / 31 assertion pada `43bbdff`).

| Skenario | Tahap | Qty | MAC tersimpan | Master (qty × MAC) | Kartu stok | GL inventory net Dr 114004 |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| 5 × 100 + 5 × 120 | Receipt kedua | 10 | 110.00 | 1100.00 | 1100.00 | 1100.00 |
| Sama | Issue 2 PCS | 8 | 110.00 | 880.00 | 880.00 | 880.00 |
| Sama | Void | 10 | 110.00 | 1100.00 | 1100.00 | 1100.00 |
| 5 × 100 + 1 × 101 | Receipt kedua | 6 | 100.17 | 601.02 | 601.00 | 601.00 |
| Sama | Issue 2 PCS | 4 | 100.17 | 400.68 | 400.68 | 400.66 |
| Sama | Void | 6 | 100.17 | 601.02 | 601.02 | 601.00 |

Kuantitas dan jurnal issue/reversal konsisten. Skenario pecahan membuktikan selisih nilai 0.02: receipt menulis nilai aktual 601.00 ke kartu/GL, tetapi menyimpan MAC 100.17 (601/6 dibulatkan ke 2 desimal). Issue menilai 2 PCS sebesar 200.34 dan menghitung saldo kartu dari qty × MAC yang dibulatkan. Void mengembalikan tepat 200.34 ke GL dan stok; selisih kartu tetap ada. Tes mencatat perilaku ini sebagai karakterisasi, bukan acceptance.

Metode moving-average terdokumentasi, tetapi tidak ditemukan aturan AUX untuk pemilik residu pembulatan, pengosongan stok, atau penanganan saldo lama. Jangan diam-diam mengganti presisi, menambah jurnal penyesuaian, atau mengubah helper lintas material. Keputusan Finance/Darwin diperlukan: apakah nilai berjalan harus mengikuti accumulator persediaan yang sama dengan GL (termasuk perlakuan saldo/residu pada issue terakhir dan void), dan apakah serta bagaimana memperbaiki saldo historis. Tidak ada perubahan runtime/skema di branch ini.

Tes delta: 3 tes / 89 assertion (dua skenario mixed-cost dan regresi single-cost karena helper fixture berubah). PHPUnit/runner exit 0; instance dan datadir disposable dibersihkan. Bukti: `D:/ERP-Recovery/finance-uat-local-895d410/o04-mixed-cost-final/` (`maria.log`, `summary.json`, `auxiliary-mixed-exact-average.json`, `auxiliary-mixed-rounding-gap.json`).
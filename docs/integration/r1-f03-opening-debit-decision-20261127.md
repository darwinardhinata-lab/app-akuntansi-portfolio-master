# F03 — keputusan Accounting: penggantian debit saldo awal

**OPEN / belum disetujui.** Ini bukan perubahan kebijakan, otorisasi koreksi, atau instruksi untuk mengubah histori. Sumber: [karakterisasi MariaDB disposable](r1-f03-residual-committed-20261127.md).

D = 27 November 2026, D+1 = 28 November 2026 (Asia/Jakarta). Contoh memakai saldo aktual dasar `500,00` dan hold Maker `400,00`. Debit saldo awal `100,01` dan arus keluar `100,01` yang sudah tercatat sama-sama efektif D+1. Penggantian menghapus debit bank itu dari proyeksi tetapi tidak mengubah arus keluar, dan tidak membuat kredit bank baru.

| Posisi | Aktual D | Tersedia D | Aktual D+1 | Tersedia D+1 | Hold |
| --- | ---: | ---: | ---: | ---: | ---: |
| Debit awal masih ada | 500,00 | 100,00 | 500,00 | 100,00 | 400,00 |
| Debit awal diganti; arus keluar tetap | 500,00 | 100,00 | 399,99 | −0,01 | 400,00 |

| Pilihan | Dampak dalam contoh | Trade-off |
| --- | --- | --- |
| Tolak penggantian jika proyeksi saldo tersedia menjadi negatif | Penolakan mempertahankan `100,00 / 100,00` dan hold `400,00` | Menjaga dana tetapi bisa menunda koreksi yang sah. Jika dipilih, tentukan horizon pemeriksaan semua tanggal pergerakan terdampak, bukan hanya D+1, serta perlakuan periode tertutup dan saldo historis yang sudah negatif. |
| Tunda koreksi sampai dana pengganti yang sah tersedia, lalu catat koreksi tertaut | Dana tambahan efektif paling lambat D+1 minimal `0,01` membuat D+1 tidak negatif; `100,01` mempertahankan `100,00` tersedia pada D+1. Pada D tetap `100,00` jika dana tambahan efektif D+1 | Menjaga hold dan jejak audit; butuh bukti sumber dana, otorisasi, tanggal efektif, serta mekanisme yang tidak meninggalkan penulisan parsial. Tidak boleh menganggap penambahan GL sebagai dana nyata tanpa rekonsiliasi. |
| Izinkan koreksi dan tangani defisit melalui pengecualian yang disetujui | D tetap `100,00`; D+1 menjadi `−0,01` dengan hold tetap `400,00` | Tidak memenuhi invariant saldo tersedia nonnegatif. Jika aturan bisnis mengizinkan pengecualian, tentukan otorisasi, blokir pembelanjaan baru sesuai kebijakan, dan tentukan siapa yang membawa saldo kembali aman. Jangan menurunkan atau melepas hold otomatis. |

Rekomendasi untuk diputuskan: tolak perubahan yang membuat defisit sampai koreksi tertaut dan sumber dana sah dapat direkonsiliasi. Catat keputusan, tanggal efektif, approver, dan aturan pengecualian di decision log sebelum ada perubahan kode atau data. Audit histori tetap baca-saja pada snapshot berizin.